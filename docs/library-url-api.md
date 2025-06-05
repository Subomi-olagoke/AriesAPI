# Library URL API Documentation

## Overview

The Library URL API allows users to add links to their libraries with automatic content summarization. When a URL is added to a library, the system fetches the content, extracts relevant information, and generates a summary, providing users with a preview of the content without needing to visit the site.

## Endpoints

### Add URL to Library

```
POST /api/libraries/{id}/urls
```

Adds a URL to a library with automatic content fetching and summarization.

#### URL Parameters

| Parameter | Type   | Description                |
|-----------|--------|----------------------------|
| id        | number | ID of the library to modify|

#### Request Body

| Field          | Type   | Required | Description                           |
|----------------|--------|----------|---------------------------------------|
| url            | string | Yes      | The URL to add to the library         |
| notes          | string | No       | Optional user notes about the URL     |
| relevance_score| number | No       | Relevance score from 0-1 (default 0.8)|

#### Example Request

```json
{
  "url": "https://example.com/article",
  "notes": "Interesting article about AI",
  "relevance_score": 0.9
}
```

#### Success Response

```json
{
  "message": "URL added to library successfully",
  "url_item": {
    "id": "url_60d2f123a4b5c",
    "url": "https://example.com/article",
    "title": "An Interesting Article About AI",
    "summary": "This article explores recent developments in artificial intelligence and their implications for society. It covers machine learning advances, ethical considerations, and potential future applications.",
    "notes": "Interesting article about AI",
    "relevance_score": 0.9,
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

### Remove URL from Library

```
DELETE /api/libraries/{id}/urls
```

Removes a URL from a library by its ID.

#### URL Parameters

| Parameter | Type   | Description                |
|-----------|--------|----------------------------|
| id        | number | ID of the library to modify|

#### Request Body

| Field  | Type   | Required | Description              |
|--------|--------|----------|--------------------------|
| url_id | string | Yes      | The ID of the URL to remove|

#### Example Request

```json
{
  "url_id": "url_60d2f123a4b5c"
}
```

#### Success Response

```json
{
  "message": "URL removed from library successfully"
}
```

## Implementation Details

The URL fetching feature uses a combination of techniques to provide high-quality summaries:

1. **Primary Content Extraction**: The system extracts the main content from HTML, focusing on article text, titles, and key information.

2. **AI-Powered Summarization**: For content that requires summarization, the system uses the OpenAI API to generate concise, accurate summaries.

3. **Fallback Mechanisms**: If direct fetching fails, the system uses the Exa search service to find information about the URL.

4. **Caching**: Summaries are cached to improve performance and reduce API usage.

## Viewing URLs in Libraries

When retrieving a library with its contents via the `/api/libraries/{id}` endpoint, URL items will be included in the `contents` array alongside courses and posts. Each URL item will have:

- `id`: A unique identifier for the URL item
- `title`: The title extracted from the web page
- `url`: The URL itself
- `description`: The summary of the content
- `type`: Will be set to "url" to distinguish from other content types
- `relevance_score`: The relevance score assigned to this URL
- `created_at`: When the URL was added to the library

## Usage Examples

### Adding a News Article

```javascript
// Example JavaScript/fetch
const response = await fetch('/api/libraries/123/urls', {
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
const response = await fetch('/api/libraries/456/urls', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: JSON.stringify({
    url: 'https://academic-journal.org/research-paper',
    notes: 'Important research for my project',
    relevance_score: 1.0  // Maximum relevance
  })
});
```

### Removing a URL

```javascript
// Example JavaScript/fetch
const response = await fetch('/api/libraries/123/urls', {
  method: 'DELETE',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: JSON.stringify({
    url_id: 'url_60d2f123a4b5c'
  })
});
```

## Notes

- The summarization length is limited to 2-3 sentences to provide a concise preview.
- URLs must be valid and accessible for the system to fetch content.
- Some websites may block automated content fetching, in which case a basic summary will be provided.
- URL items are stored as part of the library's `url_items` JSON field in the database.