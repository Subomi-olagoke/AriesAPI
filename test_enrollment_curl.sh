#!/bin/bash

# Test script for course enrollment endpoint
# This script shows the proper format for testing the enrollment endpoint

echo "=== Course Enrollment API Test ==="
echo ""

# Base URL
BASE_URL="https://ariesmvp-9903a26b3095.herokuapp.com/api"

echo "1. Testing enrollment endpoint without authentication (should fail):"
echo "curl -X POST \"$BASE_URL/courses/1/enroll\" \\"
echo "  -H \"Accept: application/json\" \\"
echo "  -H \"Content-Type: application/json\""
echo ""

curl -X POST "$BASE_URL/courses/1/enroll" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s

echo ""
echo "2. Testing enrollment endpoint with invalid token (should fail):"
echo "curl -X POST \"$BASE_URL/courses/1/enroll\" \\"
echo "  -H \"Accept: application/json\" \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -H \"Authorization: Bearer invalid-token\""
echo ""

curl -X POST "$BASE_URL/courses/1/enroll" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer invalid-token" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s

echo ""
echo "3. Proper format for testing with valid authentication:"
echo "curl -X POST \"$BASE_URL/courses/1/enroll\" \\"
echo "  -H \"Accept: application/json\" \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -H \"Authorization: Bearer YOUR_VALID_TOKEN\""
echo ""

echo "4. For free courses, the response should be:"
echo "{"
echo "  \"message\": \"Successfully enrolled in free course\","
echo "  \"enrollment\": { ... }"
echo "}"
echo ""

echo "5. For paid courses, the response should be:"
echo "{"
echo "  \"message\": \"Payment initialized\","
echo "  \"payment_url\": \"https://checkout.paystack.com/...\","
echo "  \"reference\": \"enroll_1_abc123...\","
echo "  \"enrollment_id\": 123"
echo "}"
echo ""

echo "6. To get a valid token, first login:"
echo "curl -X POST \"$BASE_URL/login\" \\"
echo "  -H \"Accept: application/json\" \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{\"email\": \"your-email@example.com\", \"password\": \"your-password\"}'"
echo ""

echo "7. Then use the token from the login response in the enrollment request"
echo "" 