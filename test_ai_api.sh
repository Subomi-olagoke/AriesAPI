#!/bin/bash

# Test script for AI Article Features API
# Make executable with: chmod +x test_ai_api.sh

API_URL="https://ariesapi-production.up.railway.app/api"
TOKEN="YOUR_AUTH_TOKEN_HERE"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Sample article content
ARTICLE_CONTENT="Artificial intelligence is revolutionizing the way we interact with technology. Machine learning algorithms can now process vast amounts of data and identify patterns that humans might miss. This has led to breakthroughs in fields ranging from healthcare to autonomous vehicles. However, as AI becomes more sophisticated, questions about ethics, privacy, and the future of work become increasingly important. How will society adapt to these changes? What safeguards need to be put in place? These are critical questions that we must address as we move forward into an AI-powered future."

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   AI Article Features API Test        ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

# Test 1: Summarize Article
echo -e "${GREEN}1. Testing Article Summarization...${NC}"
echo ""
curl -X POST "${API_URL}/articles/summarize" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d "{
    \"content\": \"${ARTICLE_CONTENT}\",
    \"url\": \"https://example.com/ai-article\"
  }" | jq '.'

echo ""
echo "-----------------------------------"
echo ""

# Test 2: Ask Question
echo -e "${GREEN}2. Testing Question Answering...${NC}"
echo ""
curl -X POST "${API_URL}/articles/ask" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d "{
    \"content\": \"${ARTICLE_CONTENT}\",
    \"question\": \"What are the main concerns about AI mentioned in the article?\"
  }" | jq '.'

echo ""
echo "-----------------------------------"
echo ""

# Test 3: Suggested Questions
echo -e "${GREEN}3. Testing Suggested Questions...${NC}"
echo ""
curl -X POST "${API_URL}/articles/suggested-questions" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d "{
    \"content\": \"${ARTICLE_CONTENT}\"
  }" | jq '.'

echo ""
echo "-----------------------------------"
echo ""

# Test 4: AI Stats
echo -e "${GREEN}4. Testing AI Stats...${NC}"
echo ""
curl -X GET "${API_URL}/articles/ai-stats" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" | jq '.'

echo ""
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   All Tests Complete                  ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
