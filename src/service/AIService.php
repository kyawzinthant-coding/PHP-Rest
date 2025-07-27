<?php

namespace App\Service;

use GuzzleHttp\Client;
use App\Repository\Product\ProductRepository;
use App\Utils\ImageUrlHelper;

class AIService
{
    private const GEMINI_API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
    private string $apiKey;
    private Client $httpClient;
    private ProductRepository $productRepository;

    public function __construct()
    {
        $this->apiKey = getenv('GEMINI_API_KEY');
        $this->httpClient = new Client();
        $this->productRepository = new ProductRepository();
    }

    public function getStructuredReply(string $userMessage): array
    {
        if (empty($this->apiKey)) {
            return ['reply' => "AI service is not configured.", 'products' => []];
        }

        $products = $this->productRepository->GetAllProduct();
        $systemPrompt = $this->createSystemPrompt($products);
        $fullPrompt = $systemPrompt . "\n\nCustomer's Question: \"" . $userMessage . "\"";

        try {
            $payload = ['contents' => [['parts' => [['text' => $fullPrompt]]]]];
            $response = $this->httpClient->post(
                self::GEMINI_API_ENDPOINT . '?key=' . $this->apiKey,
                ['json' => $payload, 'headers' => ['Content-Type' => 'application/json']]
            );

            $body = json_decode($response->getBody()->getContents(), true);
            $aiTextResponse = $body['candidates'][0]['content']['parts'][0]['text'] ?? "I'm sorry, I couldn't find a suitable recommendation.";

            $recommendedProductNames = $this->parseProductNamesFromResponse($aiTextResponse);
            $recommendedProducts = [];

            if (!empty($recommendedProductNames)) {
                $recommendedProducts = $this->productRepository->findManyByName($recommendedProductNames);
                foreach ($recommendedProducts as &$product) {
                    $product['image_url'] = ImageUrlHelper::generateUrl($product['cloudinary_public_id']);
                }
            }

            return [
                'reply' => $aiTextResponse,
                'products' => $recommendedProducts
            ];
        } catch (\Exception $e) {
            error_log("Gemini API Error: " . $e->getMessage());
            return ['reply' => "I'm experiencing a technical difficulty.", 'products' => []];
        }
    }



    private function createSystemPrompt(array $products): string
    {

        $prompt = "You are a friendly and knowledgeable fragrance expert for 'Aura Perfumes'. Your primary goal is to help customers. Follow these rules strictly:\n";
        $prompt .= "1.  **Stay On Topic:** Only answer questions about perfumes, our store, or the products listed below. If asked about anything else (e.g., math, history, other companies), you MUST politely refuse by saying something like, 'I can only help with questions about our perfumes.'\n";
        $prompt .= "2.  **Use Only Provided Data:** Base all your recommendations and answers ONLY on the product list provided. Do not invent products or details.\n";
        $prompt .= "3.  **Handle Comparisons:** If asked for the 'cheapest' or 'most expensive' product, find it in the list and state its name and price.\n";
        $prompt .= "4.  **Recommend Multiple Products:** If multiple products match a user's request, recommend all of them.\n";
        $prompt .= "5.  **Format Your Output:** After your conversational answer, you MUST list the exact names of any products you mentioned in a special format on a new line, like this: Recommended Products: [Product Name 1], [Product Name 2]\n\n";
        $prompt .= "Here is the current list of available perfumes:\n";

        $productList = array_map(function ($p) {
            return "- Name: {$p['name']}, Brand: {$p['brand_name']}, Notes: {$p['top_notes']}, {$p['middle_notes']}, {$p['base_notes']}, Price: \${$p['price']}";
        }, $products);
        $prompt .= implode("\n", $productList);

        return $prompt;
    }

    private function parseProductNamesFromResponse(string $responseText): array
    {

        if (preg_match('/Recommended Products: \[?(.*?)\]?$/', trim($responseText), $matches)) {
            $productNamesString = $matches[1];

            return array_map('trim', explode(',', $productNamesString));
        }
        return [];
    }
}
