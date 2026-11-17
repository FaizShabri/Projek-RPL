<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatLog;
use App\Services\Chatbot\AnthropicService;
use App\Services\Chatbot\SensitiveDataMasker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    protected AnthropicService $anthropicService;

    public function __construct(AnthropicService $anthropicService)
    {
        $this->anthropicService = $anthropicService;
    }

    /**
     * Endpoint utama mengirim pesan ke AI Chatbot.
     * POST /api/chatbot/message
     */
    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array|max:10',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:3000',
        ]);

        $user = $request->user();
        $activeRole = $request->header('X-Active-Role') ?: ($request->active_role ?: 'customer');

        // Pastikan user memiliki role tersebut
        if (!$user->hasRole($activeRole)) {
            return response()->json([
                'message' => 'Role aktif tidak valid untuk pengguna ini.',
            ], 403);
        }

        $userMessage = trim($validated['message']);
        $history = $validated['history'] ?? [];

        try {
            // Jalankan AI Chatbot melalui Anthropic Claude
            $result = $this->anthropicService->chat($userMessage, $history, $user, $activeRole);

            // Audit & logging dengan Masking Data Sensitif
            $maskedUserMessage = SensitiveDataMasker::mask($userMessage);
            $maskedReply = SensitiveDataMasker::mask($result['reply'] ?? '');

            ChatLog::create([
                'user_id' => $user->id,
                'active_role' => $activeRole,
                'user_message' => $maskedUserMessage,
                'assistant_message' => $maskedReply,
                'tools_used' => $result['tools_used'] ?? [],
                'token_usage' => $result['token_usage'] ?? null,
                'created_at' => now(),
            ]);

            return response()->json([
                'reply' => $result['reply'],
                'tools_used' => $result['tools_used'] ?? [],
                'role' => $activeRole,
                'is_demo' => $result['is_demo'] ?? false,
            ]);

        } catch (\Throwable $e) {
            Log::error('Chatbot Controller Exception', [
                'user_id' => $user->id,
                'role' => $activeRole,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Maaf, terjadi kendala saat memproses pertanyaan Anda ke asisten AI.',
                'reply' => 'Maaf, asisten AI sedang mengalami kendala teknis saat menghubungi server. Silakan coba sesaat lagi.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'is_demo' => false,
            ], 500);
        }
    }
}
