# Article AI Features API Documentation

## Overview

GPT-powered AI features for articles to enhance native functionality and user experience.

### Features
- ✅ **Article Summarization** - Auto-generate concise summaries with key points
- ✅ **Question Answering** - Ask questions about article content
- ✅ **Suggested Questions** - AI-generated relevant questions

---

## Authentication

All endpoints require authentication via Sanctum token:

```
Authorization: Bearer {token}
```

---

## Endpoints

### 1. Summarize Article

Generate a concise summary with key points from article content.

**Endpoint:** `POST /api/articles/summarize`

**Request Body:**
```json
{
  "content": "Full article text content here...",
  "url": "https://example.com/article" // optional, used for caching
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "summary": "This article discusses...",
    "key_points": [
      "Main point 1",
      "Main point 2",
      "Main point 3"
    ],
    "reading_time": 5,
    "word_count": 1000,
    "is_fallback": false
  }
}
```

**Features:**
- Caches summaries for 7 days (by URL)
- Falls back to extractive summary if AI unavailable
- Returns reading time estimate (200 words/min)

---

### 2. Ask Question About Article

Ask questions about article content with conversational context.

**Endpoint:** `POST /api/articles/ask`

**Request Body:**
```json
{
  "content": "Full article text content here...",
  "question": "What is the main argument of this article?",
  "conversation_history": [ // optional, for multi-turn conversations
    {
      "question": "What is this article about?",
      "answer": "This article is about..."
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "answer": "The main argument of this article is...",
    "confidence": "high",
    "question": "What is the main argument of this article?",
    "timestamp": "2026-01-23T12:00:00Z"
  }
}
```

**Features:**
- Maintains conversation context
- Indicates confidence level
- Only answers based on article content
- Clearly states if answer not in article

---

### 3. Get Suggested Questions

Get AI-generated relevant questions for an article.

**Endpoint:** `POST /api/articles/suggested-questions`

**Request Body:**
```json
{
  "content": "Full article text content here..."
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "questions": [
      "What's the main point of this article?",
      "What evidence supports the argument?",
      "How does this relate to current events?",
      "What are the key takeaways?",
      "Can you explain this in simpler terms?"
    ]
  }
}
```

**Features:**
- 5 contextually relevant questions
- Falls back to generic questions if AI unavailable
- Helps users discover aspects of the article

---

### 4. Get AI Stats

Get AI feature availability and usage statistics.

**Endpoint:** `GET /api/articles/ai-stats`

**Response:**
```json
{
  "success": true,
  "data": {
    "features_available": {
      "summarization": true,
      "question_answering": true,
      "suggested_questions": true
    },
    "limits": {
      "daily_summaries": 100,
      "daily_questions": 200
    },
    "usage": {
      "summaries_today": 5,
      "questions_today": 12
    }
  }
}
```

---

## Error Responses

### Validation Error (422)
```json
{
  "message": "Validation failed",
  "errors": {
    "content": ["The content field is required."],
    "question": ["The question field is required."]
  }
}
```

### Server Error (500)
```json
{
  "success": false,
  "message": "Failed to summarize article",
  "error": "Internal server error"
}
```

---

## Configuration

### Environment Variables

Add to `.env`:

```env
# OpenAI Configuration
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
OPENAI_ENDPOINT=https://api.openai.com/v1
```

### Model Selection

**Current Model:** `gpt-4o-mini`
- Cost-effective (~$0.15 per 1M input tokens)
- Fast response times
- High quality outputs

**Alternative Models:**
- `gpt-4o` - Higher quality, slower, more expensive
- `gpt-3.5-turbo` - Faster, cheaper, slightly lower quality

---

## Caching Strategy

### Article Summaries
- **Cache Duration:** 7 days
- **Cache Key:** `article_summary_{md5(url)}`
- **Strategy:** URL-based caching
- **Benefit:** Reduces API costs for popular articles

### Conversation Context
- **Storage:** In-memory per request
- **Max History:** Last 5 Q&A pairs
- **Purpose:** Maintains conversational flow

---

## Cost Optimization

### Token Usage

**Typical Costs (GPT-4o-mini):**
- Article Summary: 500-1500 tokens = $0.0001-0.0003
- Question Answer: 300-800 tokens = $0.00006-0.00016
- Suggested Questions: 200-400 tokens = $0.00004-0.00008

**Monthly Estimate (1000 users):**
- 10,000 summaries/month: ~$2
- 30,000 questions/month: ~$3
- Total: ~$5-10/month

### Optimization Techniques

1. **Caching** - Reuse summaries for popular articles
2. **Text Truncation** - Limit input to 10-12k characters
3. **Model Selection** - Use cost-effective models
4. **Rate Limiting** - Prevent abuse
5. **Fallback Mode** - Extractive summaries when AI unavailable

---

## Fallback Behavior

### When AI is Unavailable

**Summarization:**
- Extracts first 3 sentences as summary
- Returns generic key points
- Sets `is_fallback: true` flag

**Question Answering:**
- Returns error message
- Suggests trying again later
- Sets `confidence: low`

**Suggested Questions:**
- Returns 5 generic questions
- Still provides value to users

---

## Implementation Notes

### Service Layer

**ArticleAIService** (`app/Services/ArticleAIService.php`)
- Handles OpenAI API communication
- Manages caching and fallbacks
- Text processing and truncation

### Controller

**ArticleAIController** (`app/Http/Controllers/ArticleAIController.php`)
- Validates requests
- Logs usage
- Handles errors gracefully

### Routes

All routes under `/api/articles/` prefix:
- Require authentication (`auth:sanctum`)
- JSON request/response format
- RESTful conventions

---

## Testing

### Manual Testing

```bash
# 1. Summarize an article
curl -X POST https://your-api.com/api/articles/summarize \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Your article text here...",
    "url": "https://example.com/article"
  }'

# 2. Ask a question
curl -X POST https://your-api.com/api/articles/ask \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Your article text here...",
    "question": "What is this about?"
  }'

# 3. Get suggested questions
curl -X POST https://your-api.com/api/articles/suggested-questions \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Your article text here..."
  }'
```

---

## Next Steps

### iOS Integration

1. **Create Swift Models**
   - `ArticleSummary`
   - `AIQuestion`
   - `AIAnswer`

2. **Build API Client**
   - `ArticleAIService.swift`
   - Network layer integration

3. **UI Components**
   - Summary card view
   - Chat interface
   - Suggested questions chips

4. **Integration Points**
   - `NativeReaderView`
   - `NativeArticleReaderView`
   - Toolbar buttons

### Future Enhancements

- [ ] Smart highlights (AI suggests important passages)
- [ ] Related content discovery
- [ ] Auto-tagging and categorization
- [ ] Study notes generation
- [ ] Multi-language support
- [ ] Voice interaction
- [ ] Usage analytics dashboard

---

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Verify API key configuration
3. Test with fallback mode
4. Review token usage

---

**Last Updated:** January 23, 2026
**API Version:** 1.0
**Model:** GPT-4o-mini
