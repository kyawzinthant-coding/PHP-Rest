<?php

namespace App\Controller\Chatbot;

use App\Core\Request;
use App\Service\AIService;

class ChatbotController
{
    private AIService $aiService;

    public function __construct()
    {
        $this->aiService = new AIService();
    }

    public function handleQuery(Request $request): void
    {
        $data = json_decode($request->body, true);
        $message = $data['message'] ?? '';

        if (empty($message)) {
            http_response_code(400);
            echo json_encode(['reply' => 'A message is required.']);
            return;
        }


        $structuredResponse = $this->aiService->getStructuredReply($message);

        echo json_encode($structuredResponse);
    }
}
