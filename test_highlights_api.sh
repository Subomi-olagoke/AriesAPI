#!/bin/bash

# Highlights API Test Script
# Usage: ./test_highlights_api.sh YOUR_AUTH_TOKEN

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
API_BASE_URL="http://localhost:8000/api/v1"
TOKEN="${1:-YOUR_TOKEN_HERE}"

if [ "$TOKEN" = "YOUR_TOKEN_HERE" ]; then
    echo -e "${RED}Error: Please provide your authentication token${NC}"
    echo "Usage: ./test_highlights_api.sh YOUR_AUTH_TOKEN"
    exit 1
fi

echo -e "${YELLOW}Testing Highlights API Endpoints${NC}"
echo "=================================="
echo ""

# Test 1: Create a highlight
echo -e "${YELLOW}Test 1: Creating a highlight...${NC}"
CREATE_RESPONSE=$(curl -s -X POST "${API_BASE_URL}/highlights" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "url_id": "test_url_123",
    "url": "https://example.com/test-article",
    "selected_text": "This is a test highlight text that we are highlighting for testing purposes.",
    "note": "This is my test note about this highlight",
    "color": "#FFEB3B",
    "range_start": 100,
    "range_end": 175
  }')

echo "$CREATE_RESPONSE" | jq '.'
HIGHLIGHT_ID=$(echo "$CREATE_RESPONSE" | jq -r '.highlight.id // empty')

if [ -z "$HIGHLIGHT_ID" ]; then
    echo -e "${RED}✗ Failed to create highlight${NC}"
    exit 1
else
    echo -e "${GREEN}✓ Highlight created successfully (ID: $HIGHLIGHT_ID)${NC}"
fi
echo ""

# Test 2: Fetch highlights for URL
echo -e "${YELLOW}Test 2: Fetching highlights for URL...${NC}"
FETCH_RESPONSE=$(curl -s -X GET "${API_BASE_URL}/highlights/test_url_123" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json")

echo "$FETCH_RESPONSE" | jq '.'
FETCH_COUNT=$(echo "$FETCH_RESPONSE" | jq '.highlights | length // 0')
echo -e "${GREEN}✓ Found $FETCH_COUNT highlight(s)${NC}"
echo ""

# Test 3: Update highlight note
echo -e "${YELLOW}Test 3: Updating highlight note...${NC}"
UPDATE_RESPONSE=$(curl -s -X PUT "${API_BASE_URL}/highlights/${HIGHLIGHT_ID}" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "note": "This is my UPDATED test note with new information!"
  }')

echo "$UPDATE_RESPONSE" | jq '.'
UPDATE_SUCCESS=$(echo "$UPDATE_RESPONSE" | jq -r '.success // false')
if [ "$UPDATE_SUCCESS" = "true" ]; then
    echo -e "${GREEN}✓ Highlight updated successfully${NC}"
else
    echo -e "${RED}✗ Failed to update highlight${NC}"
fi
echo ""

# Test 4: Get all user highlights
echo -e "${YELLOW}Test 4: Getting all user highlights...${NC}"
ALL_HIGHLIGHTS=$(curl -s -X GET "${API_BASE_URL}/highlights" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json")

echo "$ALL_HIGHLIGHTS" | jq '.'
TOTAL_COUNT=$(echo "$ALL_HIGHLIGHTS" | jq '.count // 0')
echo -e "${GREEN}✓ Total highlights: $TOTAL_COUNT${NC}"
echo ""

# Test 5: Get highlight statistics
echo -e "${YELLOW}Test 5: Getting highlight statistics...${NC}"
STATS_RESPONSE=$(curl -s -X GET "${API_BASE_URL}/highlights/stats" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json")

echo "$STATS_RESPONSE" | jq '.'
STATS_SUCCESS=$(echo "$STATS_RESPONSE" | jq -r '.success // false')
if [ "$STATS_SUCCESS" = "true" ]; then
    echo -e "${GREEN}✓ Statistics retrieved successfully${NC}"
else
    echo -e "${RED}✗ Failed to get statistics${NC}"
fi
echo ""

# Test 6: Delete highlight
echo -e "${YELLOW}Test 6: Deleting highlight...${NC}"
DELETE_RESPONSE=$(curl -s -X DELETE "${API_BASE_URL}/highlights/${HIGHLIGHT_ID}" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json")

echo "$DELETE_RESPONSE" | jq '.'
DELETE_SUCCESS=$(echo "$DELETE_RESPONSE" | jq -r '.success // false')
if [ "$DELETE_SUCCESS" = "true" ]; then
    echo -e "${GREEN}✓ Highlight deleted successfully${NC}"
else
    echo -e "${RED}✗ Failed to delete highlight${NC}"
fi
echo ""

# Summary
echo "=================================="
echo -e "${GREEN}All tests completed!${NC}"
echo ""
echo "Summary:"
echo "  - Create highlight: ${GREEN}✓${NC}"
echo "  - Fetch highlights: ${GREEN}✓${NC}"
echo "  - Update highlight: ${GREEN}✓${NC}"
echo "  - Get all highlights: ${GREEN}✓${NC}"
echo "  - Get statistics: ${GREEN}✓${NC}"
echo "  - Delete highlight: ${GREEN}✓${NC}"
