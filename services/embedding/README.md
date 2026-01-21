# Customer Name Embedding Service

A Python microservice that uses SentenceTransformer (all-MiniLM-L6-v2) to compute semantic similarity between customer names. This is used as a fallback when Levenshtein-based matching fails to find confident matches.

## Architecture

```
Laravel App → Levenshtein Match (fast, ≥85% threshold)
                    ↓ (no match found)
              Embedding Service → Semantic Match (slower, more accurate)
```

## Installation

### 1. Create Python virtual environment

```bash
cd /opt/mspreports/services/embedding
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### 2. Download the model (first run will download ~90MB)

```bash
python -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('all-MiniLM-L6-v2')"
```

### 3. Run the service

**Development:**
```bash
python embedding_service.py
```

**Production (with Gunicorn):**
```bash
gunicorn -w 2 -b 0.0.0.0:5050 embedding_service:app
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `EMBEDDING_MODEL` | `all-MiniLM-L6-v2` | SentenceTransformer model name |
| `EMBEDDING_SERVICE_PORT` | `5050` | Port to run the service on |
| `REDIS_HOST` | `localhost` | Redis host for caching embeddings |
| `REDIS_PORT` | `6379` | Redis port |
| `REDIS_EMBEDDING_DB` | `2` | Redis database number for embeddings |
| `EMBEDDING_CACHE_TTL` | `604800` | Cache TTL in seconds (7 days) |
| `MIN_SIMILARITY_THRESHOLD` | `0.5` | Minimum similarity score (0-1) |

## API Endpoints

### GET /health
Health check endpoint.

### POST /embed
Get embeddings for a list of names.

```json
{
    "names": ["Customer A", "Customer B"]
}
```

### POST /similarity
Compute similarity matrix between queries and candidates.

```json
{
    "queries": ["Unmapped Customer 1"],
    "candidates": ["Existing Customer A", "Existing Customer B"]
}
```

### POST /find-matches
Find best matches for unmapped customers (main endpoint for Laravel).

```json
{
    "unmapped": [
        {"id": 1, "name": "ACME Corp"},
        {"id": 2, "name": "Globex Industries"}
    ],
    "existing": [
        {"id": 101, "name": "ACME Corporation"},
        {"id": 102, "name": "Globex Inc"}
    ],
    "threshold": 0.6,
    "top_n": 3
}
```

Response:
```json
{
    "matches": [
        {
            "unmapped_id": 1,
            "unmapped_name": "ACME Corp",
            "suggestions": [
                {"id": 101, "name": "ACME Corporation", "score": 92.1}
            ]
        }
    ]
}
```

### POST /warmup
Pre-cache embeddings for existing customers.

```json
{
    "names": ["Customer A", "Customer B", ...]
}
```

## Systemd Service

```bash
sudo cp /opt/mspreports/systemd/mspreports-embedding.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable mspreports-embedding
sudo systemctl start mspreports-embedding
```

## LLM Agent Matching

For difficult cases (initials, abbreviations), an LLM agent with tools can be used:

### Requirements
- Ollama installed and running
- Model: `qwen2.5:7b` (or configure via `OLLAMA_MODEL`)

### Install Ollama
```bash
curl -fsSL https://ollama.com/install.sh | sh
ollama pull qwen2.5:7b
```

### Agent Endpoints

**GET /agent-status** - Check if agent is available

**POST /agent-match** - Use LLM agent to match customers
```json
{
    "unmapped": [{"id": 1, "name": "TTS"}],
    "existing": [{"id": 101, "name": "Territory Technology Solutions"}]
}
```

### How the Agent Works

The agent has access to these tools:
- `search_fuzzy(name)` - Find similar spellings
- `search_initials(letters)` - Match by first letters (TTS → Territory Technology Solutions)
- `search_contains(substring)` - Partial name search
- `search_word(word)` - Find customers containing a word
- `submit_match(name, confidence, reasoning)` - Submit final decision

Example flow:
```
LLM sees: "TTS"
LLM thinks: "Looks like initials"
LLM calls: search_initials("TTS")
Tool returns: ["Territory Technology Solutions"]
LLM calls: submit_match("Territory Technology Solutions", 95, "TTS matches initials")
```

## Matching Pipeline

The full matching pipeline in Laravel:

```
1. Levenshtein (fast, ≥85% threshold)
   ↓ no match
2. Embeddings (semantic similarity, ≥60% threshold)
   ↓ no match
3. LLM Agent (reasoning with tools, optional)
   ↓ no match
4. Manual mapping required
```

## Performance Notes

- SentenceTransformer model loads ~90MB into memory
- First request may be slow (model loading)
- Embeddings are cached in Redis for 7 days
- CPU-only, no GPU required
- Batch processing is efficient (100s of names in <1 second)
- Agent matching is slower (~2-5 seconds per name) - use sparingly
