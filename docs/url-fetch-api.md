# URL Fetch API Documentation

## Overview

The URL Fetch API allows users to add links to their readlists with automatic content summarization. When a URL is added to a readlist, the system fetches the content, extracts relevant information, and generates a summary, providing users with a preview of the content without needing to visit the site.

## Endpoints

### Add URL to Readlist

```
POST /api/readlists/{id}/urls
```

Adds a URL to a readlist with automatic content fetching and summarization.

#### URL Parameters

| Parameter | Type   | Description                  |
|-----------|--------|------------------------------|
| id        | number | ID of the readlist to modify |

#### Request Body

| Field  | Type   | Required | Description                           |
|--------|--------|----------|---------------------------------------|
| url    | string | Yes      | The URL to add to the readlist        |
| notes  | string | No       | Optional user notes about the URL     |
| order  | number | No       | Position in the readlist (0-indexed)  |

#### Example Request

```json
{
  "url": "https://example.com/article",
  "notes": "Interesting article about AI",
  "order": 2
}
```

#### Success Response

```json
{
  "message": "URL added to readlist",
  "readlist_item": {
    "id": 123,
    "readlist_id": 456,
    "type": "url",
    "url": "https://example.com/article",
    "title": "An Interesting Article About AI",
    "description": "This article explores recent developments in artificial intelligence and their implications for society. It covers machine learning advances, ethical considerations, and potential future applications.",
    "notes": "Interesting article about AI",
    "order": 2,
    "created_at": "2025-06-05T12:34:56.000000Z",
    "updated_at": "2025-06-05T12:34:56.000000Z"
  },
  "url_data": {
    "title": "An Interesting Article About AI",
    "summary": "This article explores recent developments in artificial intelligence and their implications for society. It covers machine learning advances, ethical considerations, and potential future applications.",
    "url": "https://example.com/article"
  }
}
```

#### Error Responses

| Code | Description                         |
|------|-------------------------------------|
| 404  | Readlist not found                  |
| 403  | Permission denied                   |
| 422  | Validation error                    |
| 500  | Failed to fetch URL or server error |

## Implementation Details

The URL Fetch feature uses a combination of techniques to provide high-quality summaries:

1. **Primary Content Extraction**: The system extracts the main content from HTML, focusing on article text, titles, and key information.

2. **AI-Powered Summarization**: For content that requires summarization, the system uses the OpenAI API to generate concise, accurate summaries.

3. **Fallback Mechanisms**: If direct fetching fails, the system uses the Exa search service to find information about the URL.

4. **Caching**: Summaries are cached to improve performance and reduce API usage.

## Usage Examples

### Adding a News Article

```javascript
// Example JavaScript/fetch
const response = await fetch('/api/readlists/123/urls', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: JSON.stringify({
    url: 'https://news-site.com/important-article',
    notes: 'Must read later'
  })
});
```

### Adding a Research Paper

```javascript
// Example JavaScript/fetch
const response = await fetch('/api/readlists/456/urls', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: JSON.stringify({
    url: 'https://academic-journal.org/research-paper',
    notes: 'Important research for my project',
    order: 0  // Place at the beginning of the readlist
  })
});
```

## Notes

- The summarization length is limited to 2-3 sentences to provide a concise preview.
- URLs must be valid and accessible for the system to fetch content.
- Some websites may block automated content fetching, in which case a basic summary will be provided.