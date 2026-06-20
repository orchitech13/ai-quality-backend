<?php

namespace App\Http\Controllers;

use App\Models\InspectionReport;
use Illuminate\Http\Request;
use GuzzleHttp\Client;


class InspectionController extends Controller
{

    public function analyze(Request $request)
    {
        $product = $request->product_name;
        $defect = $request->defect_type;
        $description = trim($request->description);

        if (
            strlen($description) < 10 ||
            str_word_count($description) < 3
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Description must contain a meaningful defect description.'
            ], 422);
        }

        $prompt = "
            You are a manufacturing quality control AI.

            Your first task is to validate the input.

            Reject the input if:
            - description is meaningless
            - description contains random characters
            - description contains only one word
            - description is too short to identify a manufacturing defect
            - description is generic such as 'test', 'testing', 'abc', 'asd', 'hello', 'sample', 'random'
            - description does not describe a real product defect

            If input is invalid, return ONLY:

            {
            \"error\": \"INVALID_DESCRIPTION\"
            }

            If input is valid, return ONLY:

            {
            \"root_causes\": [
                \"...\"
            ],
            \"severity\": \"Low | Medium | High\",
            \"actions\": [
                \"...\"
            ]
            }

            Product: $product
            Defect: $defect
            Description: $description
            ";
        $client = new Client();

        $response = $client->post('https://api.groq.com/openai/v1/chat/completions', [
            'verify' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'llama-3.1-8b-instant',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        $aiText = $result['choices'][0]['message']['content'] ?? '';

        $aiJson = json_decode($aiText, true);

        if (isset($aiJson['error'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid defect description. Please provide more details.'
            ], 422);
        }

        // fallback
        if (!$aiJson) {
            $aiJson = [
                'root_causes' => [],
                'severity' => 'Unknown',
                'actions' => []
            ];
        }

        $report = InspectionReport::create([
            'product_name' => $product,
            'defect_type' => $defect,
            'description' => $description,
            'ai_result' => json_encode($aiJson)
        ]);

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    public function reports(Request $request)
    {
        $query = InspectionReport::query();

        if ($request->product_name) {
            $query->where('product_name', 'like', '%' . $request->product_name . '%');
        }

        if ($request->defect_type) {
            $query->where('defect_type', $request->defect_type);
        }

        $data = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function index()
    {
        return InspectionReport::latest()->get();
    }
}
