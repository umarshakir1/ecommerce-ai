# eCommerce AI Assistant — RAG-based Shopping Chatbot

A production-ready **Retrieval-Augmented Generation (RAG)** powered eCommerce AI assistant built with **Laravel 12** + **OpenRouter API**. Users describe their preferences in natural language and receive intelligent product recommendations with AI-generated explanations.

---

## Architecture

```
User Query
    │
    ├─► Intent & Entity Extraction  (OpenRouter / GPT-4o-mini)
    │       └─ { color, size, category, price_range, keywords }
    │
    ├─► Embedding Generation         (text-embedding-3-small)
    │
    ├─► Hybrid Search
    │       ├─ SQL Pre-filter        (color / size / category)
    │       └─ Cosine Similarity     (vector distance ranking)
    │
    ├─► Score Boosting               (similarity 70% + popularity 20% + price 10%)
    │
    └─► RAG Response Generation      (GPT-4o-mini + top-5 products)
            └─ { reply, products, intent }
```

---

## Project Structure

```
app/
├── Console/Commands/GenerateEmbeddings.php   # php artisan products:embed
├── Http/Controllers/ChatController.php       # POST /api/chat
├── Models/
│   ├── Product.php
│   └── Conversation.php
└── Services/
    ├── AIService.php                         # OpenRouter API wrapper
    └── VectorSearchService.php              # Cosine similarity + hybrid search

config/openrouter.php                         # API config
database/
├── migrations/
│   ├── ..._create_products_table.php
│   └── ..._create_conversations_table.php
└── seeders/ProductSeeder.php                 # 20 sample products
resources/views/chat.blade.php               # Chat UI
routes/
├── api.php                                  # API routes
└── web.php                                  # Web routes
```

---

## Setup

### Requirements
- PHP 8.2+, Composer, MySQL (XAMPP), Laravel 12

### Steps

```bash
# 1. Install dependencies (already done)
composer install

# 2. Configure environment
cp .env.example .env
# Set DB_DATABASE, OPENROUTER_API_KEY in .env

# 3. Run migrations
php artisan migrate

# 4. Seed sample products
php artisan db:seed

# 5. Generate embeddings for all products
php artisan products:embed

# 6. Start dev server
php artisan serve
```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/chat` | Send a message, get AI reply + products |
| `GET` | `/api/chat/history` | Retrieve session conversation history |
| `DELETE` | `/api/chat/history` | Clear session conversation history |

### POST /api/chat

**Request:**
```json
{ "message": "I want a black hoodie in large size" }
```

**Response:**
```json
{
  "reply": "Based on your preferences for black streetwear in size L...",
  "products": [
    {
      "id": 4,
      "name": "Black Oversized Hoodie",
      "price": 49.99,
      "color": "black",
      "size": "L",
      "similarity_score": 0.731,
      "final_score": 0.625
    }
  ],
  "intent": {
    "color": "black",
    "size": "L",
    "category": "clothing",
    "intent": "search for a black hoodie in large"
  },
  "session_id": "..."
}
```

---

## Artisan Commands

```bash
# Generate embeddings for all products without one
php artisan products:embed

# Force re-generate all embeddings
php artisan products:embed --force

# Generate for a single product
php artisan products:embed --id=5
```

---

## Environment Variables

```env
DB_CONNECTION=mysql
DB_DATABASE=ecommerce-ai
DB_USERNAME=root
DB_PASSWORD=

OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_CHAT_MODEL=openai/gpt-4o-mini
OPENROUTER_EMBEDDING_MODEL=openai/text-embedding-3-small
```

---

## Test Queries

- `"Black t-shirt large"`
- `"Outfit for 21 year old male"`
- `"Premium formal wear"`
- `"Cheap casual clothes under $30"`
- `"I am 21 years old, I like black color, and my size is large"`
