    /**
     * Handle readlist creation request from chat interface
     *
     * @param \App\Models\User $user
     * @param string $question
     * @param string $conversationId
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleReadlistCreationRequest($user, $question, $conversationId)
    {
        // Create an array to collect debug information throughout the process
        $debugLogs = [
            'process_steps' => [],
            'internal_content' => [],
            'search_results' => [],
            'validation' => [],
            'error_details' => []
        ];
        
        $description = '';
        
        try {
            $debugLogs['process_steps'][] = 'Starting readlist creation process';
            
            // Extract the topic from the question
            // Remove common phrases like "create a readlist about" to get just the topic
            $description = preg_replace('/^(cogni,?\s*)?(please\s*)?(create|make|build)(\s+a|\s+me\s+a)?\s+(readlist|reading list)(\s+for\s+me)?(\s+about|\s+on)?\s*/i', '', $question);
            $description = trim($description);
            
            // Add your implementation logic here
            
            // Sample return
            return response()->json([
                'success' => true,
                'message' => 'Readlist created successfully',
                'conversation_id' => $conversationId
            ]);
        } catch (\Exception $e) {
            return $this->handleReadlistError($e, $description ?? '', $question, $user, $conversationId);
        }
    }