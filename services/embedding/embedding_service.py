#!/usr/bin/env python3
"""
Customer Name Embedding Service

Uses SentenceTransformer (all-MiniLM-L6-v2) to compute semantic similarity
between customer names. Designed to run on CPU as a fallback when Levenshtein
matching fails to find a confident match.

API Endpoints:
- POST /embed - Get embeddings for a list of names
- POST /similarity - Compare query names against candidate names
- POST /find-matches - Find best matches for unmapped customers
- GET /health - Health check
"""

import os
import json
import hashlib
import logging
from typing import Optional

import numpy as np
from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer

# Optional Redis for caching embeddings
try:
    import redis
    REDIS_AVAILABLE = True
except ImportError:
    REDIS_AVAILABLE = False

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

app = Flask(__name__)

# Global model instance (loaded once at startup)
model: Optional[SentenceTransformer] = None
redis_client: Optional['redis.Redis'] = None

# Configuration
MODEL_NAME = os.getenv('EMBEDDING_MODEL', 'all-MiniLM-L6-v2')
REDIS_HOST = os.getenv('REDIS_HOST', 'localhost')
REDIS_PORT = int(os.getenv('REDIS_PORT', 6379))
REDIS_DB = int(os.getenv('REDIS_EMBEDDING_DB', 2))
CACHE_TTL = int(os.getenv('EMBEDDING_CACHE_TTL', 86400 * 7))  # 7 days default
MIN_SIMILARITY_THRESHOLD = float(os.getenv('MIN_SIMILARITY_THRESHOLD', 0.5))


def get_model() -> SentenceTransformer:
    """Lazy load the model on first use."""
    global model
    if model is None:
        logger.info(f"Loading SentenceTransformer model: {MODEL_NAME}")
        model = SentenceTransformer(MODEL_NAME)
        logger.info("Model loaded successfully")
    return model


def get_redis() -> Optional['redis.Redis']:
    """Get Redis client for caching embeddings."""
    global redis_client
    if not REDIS_AVAILABLE:
        return None
    if redis_client is None:
        try:
            redis_client = redis.Redis(
                host=REDIS_HOST,
                port=REDIS_PORT,
                db=REDIS_DB,
                decode_responses=False
            )
            redis_client.ping()
            logger.info("Connected to Redis for embedding cache")
        except Exception as e:
            logger.warning(f"Redis not available for caching: {e}")
            redis_client = None
    return redis_client


def normalize_name(name: str) -> str:
    """Normalize customer name for consistent embedding."""
    if not name:
        return ""
    # Remove common suffixes that don't add semantic meaning
    suffixes = [
        'pty ltd', 'pty. ltd.', 'pty ltd.', 'limited', 'ltd', 'ltd.',
        'incorporated', 'inc', 'inc.', 'corporation', 'corp', 'corp.',
        'company', 'co', 'co.', 'proprietary', 'australia', 'australian'
    ]
    normalized = name.strip().lower()
    for suffix in suffixes:
        if normalized.endswith(suffix):
            normalized = normalized[:-len(suffix)].strip()
    return normalized


def get_cache_key(name: str) -> str:
    """Generate cache key for a normalized name."""
    normalized = normalize_name(name)
    return f"emb:{hashlib.md5(normalized.encode()).hexdigest()}"


def get_cached_embedding(name: str) -> Optional[np.ndarray]:
    """Get embedding from cache if available."""
    r = get_redis()
    if r is None:
        return None
    try:
        key = get_cache_key(name)
        data = r.get(key)
        if data:
            return np.frombuffer(data, dtype=np.float32)
    except Exception as e:
        logger.warning(f"Cache read error: {e}")
    return None


def cache_embedding(name: str, embedding: np.ndarray) -> None:
    """Cache embedding for future use."""
    r = get_redis()
    if r is None:
        return
    try:
        key = get_cache_key(name)
        r.setex(key, CACHE_TTL, embedding.astype(np.float32).tobytes())
    except Exception as e:
        logger.warning(f"Cache write error: {e}")


def get_embeddings(names: list[str], use_cache: bool = True) -> np.ndarray:
    """
    Get embeddings for a list of names.
    Uses cache when available to avoid recomputation.
    """
    m = get_model()
    embeddings = []
    names_to_compute = []
    indices_to_compute = []
    
    # Check cache first
    for i, name in enumerate(names):
        if use_cache:
            cached = get_cached_embedding(name)
            if cached is not None:
                embeddings.append((i, cached))
                continue
        names_to_compute.append(normalize_name(name))
        indices_to_compute.append(i)
    
    # Compute missing embeddings in batch
    if names_to_compute:
        computed = m.encode(names_to_compute, convert_to_numpy=True, show_progress_bar=False)
        for idx, (orig_idx, name) in enumerate(zip(indices_to_compute, [names[i] for i in indices_to_compute])):
            emb = computed[idx]
            embeddings.append((orig_idx, emb))
            if use_cache:
                cache_embedding(name, emb)
    
    # Sort by original index and return
    embeddings.sort(key=lambda x: x[0])
    return np.array([e[1] for e in embeddings])


def cosine_similarity_matrix(embeddings1: np.ndarray, embeddings2: np.ndarray) -> np.ndarray:
    """
    Compute cosine similarity matrix between two sets of embeddings.
    Returns matrix of shape (len(embeddings1), len(embeddings2)).
    """
    # Normalize embeddings
    norm1 = embeddings1 / np.linalg.norm(embeddings1, axis=1, keepdims=True)
    norm2 = embeddings2 / np.linalg.norm(embeddings2, axis=1, keepdims=True)
    # Compute similarity matrix
    return np.dot(norm1, norm2.T)


@app.route('/health', methods=['GET'])
def health():
    """Health check endpoint."""
    try:
        # Verify model can be loaded
        get_model()
        return jsonify({
            'status': 'healthy',
            'model': MODEL_NAME,
            'redis_available': get_redis() is not None
        })
    except Exception as e:
        return jsonify({
            'status': 'unhealthy',
            'error': str(e)
        }), 500


@app.route('/embed', methods=['POST'])
def embed():
    """
    Get embeddings for a list of names.
    
    Request body:
    {
        "names": ["Customer A", "Customer B", ...]
    }
    
    Response:
    {
        "embeddings": [[0.1, 0.2, ...], [0.3, 0.4, ...], ...],
        "dimension": 384
    }
    """
    try:
        data = request.get_json()
        names = data.get('names', [])
        
        if not names:
            return jsonify({'error': 'No names provided'}), 400
        
        embeddings = get_embeddings(names)
        
        return jsonify({
            'embeddings': embeddings.tolist(),
            'dimension': embeddings.shape[1] if len(embeddings.shape) > 1 else 0
        })
    except Exception as e:
        logger.error(f"Embed error: {e}")
        return jsonify({'error': str(e)}), 500


@app.route('/similarity', methods=['POST'])
def similarity():
    """
    Compute similarity between query names and candidate names.
    
    Request body:
    {
        "queries": ["Unmapped Customer 1", "Unmapped Customer 2"],
        "candidates": ["Existing Customer A", "Existing Customer B", ...]
    }
    
    Response:
    {
        "similarities": [
            [0.85, 0.32, ...],  // Query 1 vs all candidates
            [0.45, 0.91, ...],  // Query 2 vs all candidates
        ]
    }
    """
    try:
        data = request.get_json()
        queries = data.get('queries', [])
        candidates = data.get('candidates', [])
        
        if not queries or not candidates:
            return jsonify({'error': 'Both queries and candidates required'}), 400
        
        query_embeddings = get_embeddings(queries)
        candidate_embeddings = get_embeddings(candidates)
        
        similarities = cosine_similarity_matrix(query_embeddings, candidate_embeddings)
        
        return jsonify({
            'similarities': similarities.tolist()
        })
    except Exception as e:
        logger.error(f"Similarity error: {e}")
        return jsonify({'error': str(e)}), 500


@app.route('/find-matches', methods=['POST'])
def find_matches():
    """
    Find best matches for unmapped customers against existing customers.
    Returns top N matches for each query above the minimum threshold.
    
    Request body:
    {
        "unmapped": [
            {"id": 1, "name": "ACME Corp"},
            {"id": 2, "name": "Globex Industries"}
        ],
        "existing": [
            {"id": 101, "name": "ACME Corporation"},
            {"id": 102, "name": "Globex Inc"}
        ],
        "threshold": 0.6,  // Optional, default 0.5
        "top_n": 3         // Optional, default 3
    }
    
    Response:
    {
        "matches": [
            {
                "unmapped_id": 1,
                "unmapped_name": "ACME Corp",
                "suggestions": [
                    {"id": 101, "name": "ACME Corporation", "score": 0.92},
                    ...
                ]
            },
            ...
        ]
    }
    """
    try:
        data = request.get_json()
        unmapped = data.get('unmapped', [])
        existing = data.get('existing', [])
        threshold = float(data.get('threshold', MIN_SIMILARITY_THRESHOLD))
        top_n = int(data.get('top_n', 3))
        
        if not unmapped or not existing:
            return jsonify({'matches': []})
        
        # Extract names
        unmapped_names = [u['name'] for u in unmapped]
        existing_names = [e['name'] for e in existing]
        
        # Get embeddings
        unmapped_embeddings = get_embeddings(unmapped_names)
        existing_embeddings = get_embeddings(existing_names)
        
        # Compute similarity matrix
        similarities = cosine_similarity_matrix(unmapped_embeddings, existing_embeddings)
        
        # Build results
        matches = []
        for i, unmapped_item in enumerate(unmapped):
            scores = similarities[i]
            
            # Get indices of top matches above threshold
            above_threshold = [(j, scores[j]) for j in range(len(scores)) if scores[j] >= threshold]
            above_threshold.sort(key=lambda x: x[1], reverse=True)
            top_matches = above_threshold[:top_n]
            
            suggestions = [
                {
                    'id': existing[j]['id'],
                    'name': existing[j]['name'],
                    'score': round(float(score) * 100, 1)  # Convert to percentage
                }
                for j, score in top_matches
            ]
            
            matches.append({
                'unmapped_id': unmapped_item['id'],
                'unmapped_name': unmapped_item['name'],
                'suggestions': suggestions
            })
        
        return jsonify({'matches': matches})
    except Exception as e:
        logger.error(f"Find matches error: {e}")
        return jsonify({'error': str(e)}), 500


@app.route('/warmup', methods=['POST'])
def warmup():
    """
    Pre-compute and cache embeddings for existing customers.
    Call this after syncing customer data to warm the cache.
    
    Request body:
    {
        "names": ["Customer A", "Customer B", ...]
    }
    
    Response:
    {
        "cached": 150,
        "message": "Embeddings cached successfully"
    }
    """
    try:
        data = request.get_json()
        names = data.get('names', [])
        
        if not names:
            return jsonify({'cached': 0, 'message': 'No names provided'})
        
        # Force compute and cache all embeddings
        get_embeddings(names, use_cache=True)
        
        return jsonify({
            'cached': len(names),
            'message': 'Embeddings cached successfully'
        })
    except Exception as e:
        logger.error(f"Warmup error: {e}")
        return jsonify({'error': str(e)}), 500


@app.route('/agent-match', methods=['POST'])
def agent_match():
    """
    Use LLM agent with tools to find matches for difficult cases.
    This is slower but more intelligent - use as last resort after
    embeddings fail.
    
    Request body:
    {
        "unmapped": [
            {"id": 1, "name": "TTS"},
            {"id": 2, "name": "ACME Corp"}
        ],
        "existing": [
            {"id": 101, "name": "Territory Technology Solutions"},
            {"id": 102, "name": "Acme Corporation"}
        ]
    }
    
    Response:
    {
        "matches": [
            {
                "unmapped_id": 1,
                "unmapped_name": "TTS",
                "suggestions": [
                    {"id": 101, "name": "Territory Technology Solutions", "score": 95, "reasoning": "TTS matches initials", "method": "agent"}
                ]
            }
        ],
        "agent_available": true
    }
    """
    try:
        from agent_matcher import batch_match_with_agent, OLLAMA_AVAILABLE
        
        if not OLLAMA_AVAILABLE:
            return jsonify({
                'matches': [],
                'agent_available': False,
                'error': 'Ollama not installed'
            })
        
        data = request.get_json()
        unmapped = data.get('unmapped', [])
        existing = data.get('existing', [])
        
        if not unmapped or not existing:
            return jsonify({'matches': [], 'agent_available': True})
        
        matches = batch_match_with_agent(unmapped, existing)
        
        return jsonify({
            'matches': matches,
            'agent_available': True
        })
    except Exception as e:
        logger.error(f"Agent match error: {e}")
        return jsonify({
            'matches': [],
            'agent_available': False,
            'error': str(e)
        }), 500


@app.route('/agent-status', methods=['GET'])
def agent_status():
    """Check if the LLM agent is available."""
    try:
        from agent_matcher import OLLAMA_AVAILABLE, OLLAMA_MODEL
        
        if not OLLAMA_AVAILABLE:
            return jsonify({
                'available': False,
                'reason': 'Ollama package not installed'
            })
        
        # Try to ping Ollama
        import ollama
        try:
            ollama.list()
            return jsonify({
                'available': True,
                'model': OLLAMA_MODEL
            })
        except Exception as e:
            return jsonify({
                'available': False,
                'reason': f'Ollama not running: {str(e)}'
            })
    except Exception as e:
        return jsonify({
            'available': False,
            'reason': str(e)
        })


if __name__ == '__main__':
    # Pre-load model on startup
    logger.info("Starting embedding service...")
    get_model()
    
    # Run Flask development server
    port = int(os.getenv('EMBEDDING_SERVICE_PORT', 5050))
    app.run(host='0.0.0.0', port=port, debug=False)
