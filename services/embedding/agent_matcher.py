#!/usr/bin/env python3
"""
LLM Agent-based Customer Matcher

Uses Ollama with tool calling to intelligently match customer names.
The LLM can reason about abbreviations, initials, and semantic meaning.

Flow:
1. Levenshtein/embedding fails to find match
2. Agent receives unmapped name + tools
3. Agent reasons and calls tools (search_initials, search_fuzzy, etc.)
4. Agent returns best match with confidence

Requires: Ollama running locally with qwen2.5 or llama3.2
"""

import json
import logging
import re
from typing import Optional
from dataclasses import dataclass

try:
    import ollama
    OLLAMA_AVAILABLE = True
except ImportError:
    OLLAMA_AVAILABLE = False

from rapidfuzz import fuzz, process

logger = logging.getLogger(__name__)

# Configuration
OLLAMA_MODEL = "qwen2.5:7b"  # Good balance of speed and capability
MAX_TOOL_CALLS = 5  # Prevent infinite loops
MIN_CONFIDENCE = 60  # Minimum confidence to accept a match


@dataclass
class MatchResult:
    customer_id: int
    customer_name: str
    confidence: int
    reasoning: str
    tool_calls: list


class CustomerMatcherAgent:
    """LLM Agent that uses tools to find customer matches."""
    
    def __init__(self, existing_customers: list[dict]):
        """
        Initialize with list of existing customers.
        
        Args:
            existing_customers: List of {"id": int, "name": str}
        """
        self.customers = existing_customers
        self.customer_names = [c["name"] for c in existing_customers]
        self.customer_map = {c["name"].lower(): c for c in existing_customers}
        
        # Build initials index for fast lookup
        self.initials_index = self._build_initials_index()
        
        # Build word index
        self.word_index = self._build_word_index()
    
    def _build_initials_index(self) -> dict[str, list[dict]]:
        """Build index of customers by their initials."""
        index = {}
        for customer in self.customers:
            words = customer["name"].split()
            if len(words) >= 2:
                initials = "".join(w[0].upper() for w in words if w)
                if initials not in index:
                    index[initials] = []
                index[initials].append(customer)
        return index
    
    def _build_word_index(self) -> dict[str, list[dict]]:
        """Build index of customers by significant words."""
        index = {}
        stop_words = {"pty", "ltd", "limited", "inc", "corp", "the", "and", "of", "for"}
        for customer in self.customers:
            words = re.findall(r'\b\w+\b', customer["name"].lower())
            for word in words:
                if word not in stop_words and len(word) > 2:
                    if word not in index:
                        index[word] = []
                    index[word].append(customer)
        return index
    
    # ========== TOOLS ==========
    
    def search_fuzzy(self, name: str, limit: int = 5) -> list[dict]:
        """Find customers with similar spelling using fuzzy matching."""
        if not name or not self.customer_names:
            return []
        
        results = process.extract(
            name, 
            self.customer_names, 
            scorer=fuzz.WRatio,
            limit=limit
        )
        
        return [
            {"name": match[0], "score": match[1], "id": self.customer_map[match[0].lower()]["id"]}
            for match in results
            if match[1] > 50
        ]
    
    def search_initials(self, initials: str) -> list[dict]:
        """Find customers whose name initials match."""
        initials = initials.upper().replace(" ", "").replace(".", "")
        matches = self.initials_index.get(initials, [])
        return [{"id": c["id"], "name": c["name"]} for c in matches]
    
    def search_contains(self, substring: str) -> list[dict]:
        """Find customers whose name contains a substring."""
        substring = substring.lower()
        matches = [
            {"id": c["id"], "name": c["name"]}
            for c in self.customers
            if substring in c["name"].lower()
        ]
        return matches[:10]  # Limit results
    
    def search_word(self, word: str) -> list[dict]:
        """Find customers whose name contains a specific word."""
        word = word.lower()
        matches = self.word_index.get(word, [])
        return [{"id": c["id"], "name": c["name"]} for c in matches[:10]]
    
    def get_customer_count(self) -> int:
        """Get total number of existing customers."""
        return len(self.customers)
    
    # ========== TOOL DEFINITIONS FOR LLM ==========
    
    TOOLS = [
        {
            "type": "function",
            "function": {
                "name": "search_fuzzy",
                "description": "Find customers with similar spelling using fuzzy matching. Good for typos and minor variations.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "name": {
                            "type": "string",
                            "description": "The customer name to search for"
                        }
                    },
                    "required": ["name"]
                }
            }
        },
        {
            "type": "function",
            "function": {
                "name": "search_initials",
                "description": "Find customers whose name initials match. Use when the input looks like initials (e.g., 'TTS' could be 'Territory Technology Solutions').",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "initials": {
                            "type": "string",
                            "description": "The initials to search for (e.g., 'TTS', 'ABC')"
                        }
                    },
                    "required": ["initials"]
                }
            }
        },
        {
            "type": "function",
            "function": {
                "name": "search_contains",
                "description": "Find customers whose name contains a substring. Good for partial matches.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "substring": {
                            "type": "string",
                            "description": "The substring to search for"
                        }
                    },
                    "required": ["substring"]
                }
            }
        },
        {
            "type": "function",
            "function": {
                "name": "search_word",
                "description": "Find customers whose name contains a specific word. Good for finding by company type or key word.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "word": {
                            "type": "string",
                            "description": "The word to search for"
                        }
                    },
                    "required": ["word"]
                }
            }
        },
        {
            "type": "function",
            "function": {
                "name": "get_customer_count",
                "description": "Get the total number of existing customers in the database.",
                "parameters": {
                    "type": "object",
                    "properties": {}
                }
            }
        },
        {
            "type": "function",
            "function": {
                "name": "submit_match",
                "description": "Submit your final match decision. Call this when you've found the best match or determined there's no match.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "customer_name": {
                            "type": "string",
                            "description": "The exact name of the matched customer, or empty string if no match"
                        },
                        "confidence": {
                            "type": "integer",
                            "description": "Your confidence in this match (0-100). Use 0 if no match found."
                        },
                        "reasoning": {
                            "type": "string",
                            "description": "Brief explanation of why this is the best match"
                        }
                    },
                    "required": ["customer_name", "confidence", "reasoning"]
                }
            }
        }
    ]
    
    def _execute_tool(self, name: str, args: dict) -> str:
        """Execute a tool and return result as string."""
        try:
            if name == "search_fuzzy":
                result = self.search_fuzzy(args.get("name", ""))
            elif name == "search_initials":
                result = self.search_initials(args.get("initials", ""))
            elif name == "search_contains":
                result = self.search_contains(args.get("substring", ""))
            elif name == "search_word":
                result = self.search_word(args.get("word", ""))
            elif name == "get_customer_count":
                result = {"count": self.get_customer_count()}
            elif name == "submit_match":
                # This is handled specially in the main loop
                return json.dumps(args)
            else:
                result = {"error": f"Unknown tool: {name}"}
            
            return json.dumps(result, indent=2)
        except Exception as e:
            return json.dumps({"error": str(e)})
    
    def find_match(self, unmapped_name: str) -> Optional[MatchResult]:
        """
        Use LLM agent to find the best match for an unmapped customer name.
        
        Args:
            unmapped_name: The customer name to match
            
        Returns:
            MatchResult if a confident match is found, None otherwise
        """
        if not OLLAMA_AVAILABLE:
            logger.warning("Ollama not available, skipping agent matching")
            return None
        
        system_prompt = """You are a customer name matching assistant. Your job is to find if an unmapped customer name matches any existing customer in the database.

You have access to search tools to help you find matches. Use them strategically:
- If the name looks like initials (e.g., "TTS", "ABC Corp"), use search_initials
- If it's a full name with possible typos, use search_fuzzy
- If you want to find by a key word, use search_word
- If you want to find partial matches, use search_contains

After searching, call submit_match with your decision. Be conservative - only submit a match if you're confident it's correct.

Common patterns to recognize:
- Initials: "TTS" → "Territory Technology Solutions"
- Abbreviations: "Corp" = "Corporation", "Ltd" = "Limited"
- Typos: "Teritory" → "Territory"
- Partial names: "Territory Tech" → "Territory Technology Solutions"
"""

        user_prompt = f"""Find a match for this unmapped customer name: "{unmapped_name}"

Use the search tools to find potential matches, then submit your final decision."""

        messages = [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt}
        ]
        
        tool_calls_made = []
        
        try:
            for iteration in range(MAX_TOOL_CALLS):
                response = ollama.chat(
                    model=OLLAMA_MODEL,
                    messages=messages,
                    tools=self.TOOLS
                )
                
                message = response.get("message", {})
                
                # Check if LLM wants to call tools
                tool_calls = message.get("tool_calls", [])
                
                if not tool_calls:
                    # No more tool calls, LLM is done
                    logger.debug(f"Agent finished after {iteration} iterations without submitting")
                    break
                
                # Process each tool call
                for tool_call in tool_calls:
                    func = tool_call.get("function", {})
                    tool_name = func.get("name", "")
                    tool_args = func.get("arguments", {})
                    
                    logger.debug(f"Agent calling tool: {tool_name}({tool_args})")
                    tool_calls_made.append({"tool": tool_name, "args": tool_args})
                    
                    # Execute tool
                    result = self._execute_tool(tool_name, tool_args)
                    
                    # Check if this is the final submission
                    if tool_name == "submit_match":
                        submission = json.loads(result)
                        customer_name = submission.get("customer_name", "")
                        confidence = submission.get("confidence", 0)
                        reasoning = submission.get("reasoning", "")
                        
                        if not customer_name or confidence < MIN_CONFIDENCE:
                            logger.info(f"Agent found no confident match for '{unmapped_name}': {reasoning}")
                            return None
                        
                        # Find the customer ID
                        matched = self.customer_map.get(customer_name.lower())
                        if not matched:
                            # Try fuzzy match on the submitted name
                            fuzzy_results = self.search_fuzzy(customer_name, limit=1)
                            if fuzzy_results and fuzzy_results[0]["score"] > 90:
                                matched = {"id": fuzzy_results[0]["id"], "name": fuzzy_results[0]["name"]}
                        
                        if matched:
                            return MatchResult(
                                customer_id=matched["id"],
                                customer_name=matched["name"],
                                confidence=confidence,
                                reasoning=reasoning,
                                tool_calls=tool_calls_made
                            )
                        else:
                            logger.warning(f"Agent submitted unknown customer: {customer_name}")
                            return None
                    
                    # Add tool result to messages for next iteration
                    messages.append(message)
                    messages.append({
                        "role": "tool",
                        "content": result
                    })
            
            logger.warning(f"Agent hit max iterations for '{unmapped_name}'")
            return None
            
        except Exception as e:
            logger.error(f"Agent error for '{unmapped_name}': {e}")
            return None


def match_with_agent(unmapped_name: str, existing_customers: list[dict]) -> Optional[dict]:
    """
    Convenience function to match a single customer using the agent.
    
    Args:
        unmapped_name: Name to match
        existing_customers: List of {"id": int, "name": str}
        
    Returns:
        Match dict or None
    """
    if not OLLAMA_AVAILABLE:
        return None
    
    agent = CustomerMatcherAgent(existing_customers)
    result = agent.find_match(unmapped_name)
    
    if result:
        return {
            "id": result.customer_id,
            "name": result.customer_name,
            "score": result.confidence,
            "reasoning": result.reasoning,
            "method": "agent",
            "tool_calls": result.tool_calls
        }
    return None


def batch_match_with_agent(unmapped: list[dict], existing_customers: list[dict]) -> list[dict]:
    """
    Match multiple customers using the agent.
    
    Args:
        unmapped: List of {"id": int, "name": str}
        existing_customers: List of {"id": int, "name": str}
        
    Returns:
        List of match results
    """
    if not OLLAMA_AVAILABLE or not unmapped:
        return []
    
    agent = CustomerMatcherAgent(existing_customers)
    results = []
    
    for item in unmapped:
        result = agent.find_match(item["name"])
        
        match_data = {
            "unmapped_id": item["id"],
            "unmapped_name": item["name"],
            "suggestions": []
        }
        
        if result:
            match_data["suggestions"].append({
                "id": result.customer_id,
                "name": result.customer_name,
                "score": result.confidence,
                "reasoning": result.reasoning,
                "method": "agent"
            })
        
        results.append(match_data)
    
    return results


# Test the agent
if __name__ == "__main__":
    logging.basicConfig(level=logging.DEBUG)
    
    # Sample existing customers
    existing = [
        {"id": 1, "name": "Territory Technology Solutions"},
        {"id": 2, "name": "Acme Corporation"},
        {"id": 3, "name": "Global Tech Industries"},
        {"id": 4, "name": "Smith & Associates"},
        {"id": 5, "name": "Northern Territory Government"},
    ]
    
    # Test cases
    test_names = [
        "TTS",  # Should match Territory Technology Solutions
        "Teritory Tech",  # Typo
        "ACME Corp",  # Abbreviation
        "Smith Associates",  # Missing &
        "Random Company XYZ",  # No match
    ]
    
    agent = CustomerMatcherAgent(existing)
    
    for name in test_names:
        print(f"\n{'='*50}")
        print(f"Matching: {name}")
        result = agent.find_match(name)
        if result:
            print(f"  → Matched: {result.customer_name} (confidence: {result.confidence}%)")
            print(f"  → Reasoning: {result.reasoning}")
            print(f"  → Tool calls: {len(result.tool_calls)}")
        else:
            print("  → No match found")
