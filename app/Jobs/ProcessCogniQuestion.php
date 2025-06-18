<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\CogniService;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Log;

class ProcessCogniQuestion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $question;
    protected $context;
    protected $user;
    protected $conversationId;
    protected $callback;

    /**
     * Create a new job instance.
     */
    public function __construct(string $question, array $context, ?User $user, string $conversationId, ?Closure $callback = null)
    {
        $this->question = $question;
        $this->context = $context;
        $this->user = $user;
        $this->conversationId = $conversationId;
        $this->callback = $callback;
    }

    /**
     * Execute the job.
     */
    public function handle(CogniService $cogniService): void
    {
        try {
            // Add the user's question to the context before sending to CogniService
            $this->context[] = [
                'role' => 'user',
                'content' => $this->question
            ];

            $result = $cogniService->askQuestionInternal($this->question, $this->context);

            if ($result['success'] && isset($result['answer'])) {
                // Add assistant's response to context
                $this->context[] = [
                    'role' => 'assistant',
                    'content' => $result['answer']
                ];

                // Store updated conversation in session (if session-based, otherwise handle persistence)
                // For a job, session is not directly accessible, so we'll need to persist to DB
                // Keep only the last 10 messages to prevent context size issues
                if (count($this->context) > 10) {
                    if ($this->context[0]['role'] === 'system') {
                        $this->context = array_merge(
                            [$this->context[0]],
                            array_slice($this->context, -9)
                        );
                    } else {
                        $this->context = array_slice($this->context, -10);
                    }
                }

                // Store in database for persistence
                $this->storeConversationInDatabase($this->user, $this->conversationId, $this->question, $result['answer']);

                // If a callback is provided, execute it with the result
                if ($this->callback) {
                    ($this->callback)([ // Pass an array as a single argument
                        'success' => true,
                        'answer' => $result['answer'],
                        'conversation_id' => $this->conversationId,
                        'context' => $this->context // Pass updated context back
                    ]);
                }
            } else {
                Log::error('CogniService internal request failed in job', [
                    'result' => $result
                ]);
                if ($this->callback) {
                    ($this->callback)([ // Pass an array as a single argument
                        'success' => false,
                        'message' => $result['message'] ?? 'Failed to get an answer from Cogni in job',
                        'code' => $result['code'] ?? 500
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('ProcessCogniQuestion job failed: ' . $e->getMessage(), ['exception' => $e]);
            if ($this->callback) {
                ($this->callback)([ // Pass an array as a single argument
                    'success' => false,
                    'message' => 'An error occurred while processing your question asynchronously',
                    'code' => 500
                ]);
            }
        }
    }

    private function storeConversationInDatabase(?User $user, string $conversationId, string $question, string $answer)
    {
        if (!$user) {
            Log::warning('Attempted to store conversation without authenticated user for conversation ID: ' . $conversationId);
            return;
        }

        \App\Models\CogniConversation::updateOrCreate(
            [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'question' => $question,
            ],
            [
                'answer' => $answer,
                'updated_at' => now(),
            ]
        );
    }
}
