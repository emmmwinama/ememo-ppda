<?php
// FCMService.php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;

class FCMService
{
    private $serviceAccountPath;
    private $projectId;
    private $accessToken;
    private $client;

    public function __construct(string $serviceAccountPath)
    {
        $this->serviceAccountPath = $serviceAccountPath;

        if (!file_exists($this->serviceAccountPath)) {
            throw new \RuntimeException("Service account file not found: {$this->serviceAccountPath}");
        }

        $json = json_decode(file_get_contents($this->serviceAccountPath), true);
        $this->projectId = $json['project_id'] ?? null;

        if (!$this->projectId) {
            throw new \RuntimeException("Missing project_id in service account JSON");
        }

        $this->authenticate();
        $this->client = new Client(['timeout' => 10]);
    }

    private function authenticate(): void
    {
        $creds = new ServiceAccountCredentials(
            ['https://www.googleapis.com/auth/firebase.messaging'],
            $this->serviceAccountPath
        );

        $tokenArray = $creds->fetchAuthToken();
        if (!isset($tokenArray['access_token'])) {
            throw new \RuntimeException("Failed to retrieve OAuth token");
        }

        $this->accessToken = $tokenArray['access_token'];
    }

    public function sendNotification(array $tokens, string $title, string $body, array $data = []): array
    {
        if (empty($tokens)) {
            throw new \InvalidArgumentException("At least one token is required");
        }

        $results = [];
        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body
                    ],
                    'data' => array_map('strval', $data),
                    'android' => ['priority' => 'high']
                ]
            ];

            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            try {
                $resp = $this->client->post($url, [
                    'headers' => [
                        'Authorization' => "Bearer {$this->accessToken}",
                        'Content-Type'  => 'application/json; UTF-8',
                    ],
                    'body' => json_encode($payload),
                ]);

                $results[] = [
                    'token'    => $token,
                    'response' => json_decode((string)$resp->getBody(), true)
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'token' => $token,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
