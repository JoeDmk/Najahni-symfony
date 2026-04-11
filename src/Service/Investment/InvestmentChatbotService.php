<?php

namespace App\Service\Investment;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class InvestmentChatbotService
{
    private const HF_URL = 'https://router.huggingface.co/v1/chat/completions';
    private const HF_MODELS = [
        'Qwen/Qwen2.5-7B-Instruct',
        'mistralai/Mistral-7B-Instruct-v0.3',
        'meta-llama/Llama-3.1-8B-Instruct',
        'meta-llama/Llama-3.2-1B-Instruct',
    ];
    private const MAX_HISTORY = 20;
    private const MAX_RETRIES = 3;
    private const TIMEOUT = 60;

    private const SYSTEM_INSTRUCTION = <<<'PROMPT'
Tu es NAJAHNI AI, l'assistant intelligent integre dans la plateforme fintech NAJAHNI — une application web Symfony tunisienne qui connecte entrepreneurs et investisseurs. Tu connais TOUT sur cette application.

=== PLATEFORME NAJAHNI ===
- App web Symfony 7 (PHP 8), MySQL, IA integree (HuggingFace Llama 3.2)
- 2 roles : INVESTISSEUR (investir, portfolio, paiements Stripe) et ENTREPRENEUR (creer projets, opportunites, gerer offres)

=== MODULES FONCTIONNELS ===
1. **Projets** : BROUILLON → SOUMIS → EVALUE. Entrepreneurs creent des projets avec titre, description, secteur.
2. **Opportunites d'Investissement** : Liees a un projet. Statuts : OPEN → FUNDED/CLOSED. Montant cible, deadline, description.
3. **Offres d'Investissement** : Un investisseur propose un montant sur une opportunite. PENDING → ACCEPTED/REJECTED. Offres acceptees → paiement Stripe.
4. **Analyse de Risque IA** : Score 0-100 base sur montant (30%), duree (20%), facteurs economiques (50%). Donnees en temps reel : taux de change EUR/USD, PIB, inflation via APIs World Bank. Niveaux : Faible (0-33), Modere (34-66), Eleve (67-100).
5. **Matching IA** : Score de compatibilite 0-100 entre profil investisseur et opportunites. Criteres : secteur (35%), budget (25%), risque (25%), horizon (15%).

=== DONNEES ECONOMIQUES ===
- Taux de change via Open Exchange Rates (EUR base)
- PIB et Inflation via World Bank API
- 5 devises : EUR, USD, TND, GBP, MAD

=== SECTEURS PORTEURS EN TUNISIE ===
Technologie, Agriculture, Tourisme, Sante, Energie renouvelable, Industrie textile, Agroalimentaire, Services financiers.

=== REGLES ===
- Reponds TOUJOURS en francais
- Sois concis mais precis (max 4-5 phrases sauf demande de details)
- Pour les analyses d'investissement : fournis niveau de risque, avantages, inconvenients, recommandation
- Tu connais le contexte tunisien (TND, dinar tunisien, secteurs porteurs)
- Ne donne jamais de conseil juridique formel — rappelle de consulter un professionnel
- Si la question n'est pas liee a NAJAHNI ou la finance, reponds que tu es specialise dans la plateforme NAJAHNI
PROMPT;

    private array $conversationHistory = [];
    private string $hfToken;
    private ?string $workingModel = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $hfToken,
    ) {
        $this->hfToken = trim($hfToken);
    }

    public function chat(string $userMessage): string
    {
        $this->conversationHistory[] = ['role' => 'user', 'content' => $userMessage];
        $this->trimHistory();

        $response = $this->sendRequest($this->buildMessages());

        $this->conversationHistory[] = ['role' => 'assistant', 'content' => $response];

        return $response;
    }

    public function analyzeRisk(
        string $projectTitle,
        string $sector,
        float $amount,
        string $deadline,
        string $description,
        float $currentRiskScore,
    ): string {
        $prompt = sprintf(
            "Analyse de risque IA pour cet investissement :\n\n"
            . "Projet : %s\nSecteur : %s\nMontant : %.2f EUR\nDeadline : %s\nDescription : %s\n"
            . "Score de risque algorithmique actuel : %.0f/100\n\n"
            . "Fournis une analyse structuree avec :\n"
            . "1. Ton evaluation du risque (Faible/Moyen/Eleve) et pourquoi\n"
            . "2. Points forts de cet investissement (2-3 points)\n"
            . "3. Points de vigilance (2-3 points)\n"
            . "4. Recommandation finale (investir / attendre / eviter)\n"
            . "5. Ton score de confiance dans cette analyse (0-100%%)\n\n"
            . "Contexte : Plateforme d'investissement tunisienne NAJAHNI.",
            $projectTitle, $sector, $amount, $deadline, $description, $currentRiskScore
        );

        return $this->sendOneShot($prompt);
    }

    /**
     * Generate a short, plain-language risk verdict (2-3 sentences max).
     * Designed to feel like advice from a financial advisor, not a dashboard widget.
     */
    public function generateRiskVerdict(
        string $projectTitle,
        string $sector,
        float $amount,
        string $deadline,
        string $description,
        float $riskScore,
        string $riskLevel,
        array $economicContext = [],
    ): string {
        $ecoSnippet = '';
        if (!empty($economicContext)) {
            $ecoSnippet = sprintf(
                "\nContexte economique : pays %s, inflation %.1f%%, PIB %.1f Mrd $, taux EUR/USD %.4f.",
                $economicContext['country'] ?? 'inconnu',
                $economicContext['inflation'] ?? 0,
                $economicContext['gdp'] ?? 0,
                $economicContext['eurUsd'] ?? 0,
            );
        }

        $prompt = sprintf(
            "Tu es un conseiller financier sur la plateforme NAJAHNI. "
            . "Redige un verdict en 2 a 3 phrases maximum, en francais, comme si tu parlais directement a un investisseur. "
            . "Pas de listes, pas de titres, pas de puces, juste du texte naturel. "
            . "Sois honnete et direct. Si le risque est eleve, dis-le clairement. Si c'est prometteur, dis-le aussi.\n\n"
            . "Projet : %s\nSecteur : %s\nMontant : %.0f DT\nDeadline : %s\nDescription : %s\n"
            . "Score de risque algorithmique : %.0f/100 (%s)%s",
            $projectTitle, $sector, $amount, $deadline, $description, $riskScore, $riskLevel, $ecoSnippet
        );

        return $this->sendOneShot($prompt, 200);
    }

    public function clearHistory(): void
    {
        $this->conversationHistory = [];
    }

    public function isFailureResponse(string $response): bool
    {
        return preg_match(
            '/IA temporairement indisponible|IA indisponible|Authentification Hugging Face invalide|Le chatbot IA n\'est pas configure|Quota IA atteint|Aucun modele compatible|Requete IA invalide/i',
            $response
        ) === 1;
    }

    public function isConfigured(): bool
    {
        return $this->hfToken !== '' && $this->hfToken !== 'your_huggingface_token_here';
    }

    private function sendOneShot(string $prompt, int $maxTokens = 512): string
    {
        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_INSTRUCTION],
            ['role' => 'user', 'content' => $prompt],
        ];
        return $this->sendRequest($messages, $maxTokens);
    }

    private function buildMessages(): array
    {
        $messages = [['role' => 'system', 'content' => self::SYSTEM_INSTRUCTION]];
        foreach ($this->conversationHistory as $turn) {
            $messages[] = $turn;
        }
        return $messages;
    }

    private function sendRequest(array $messages, int $maxTokens = 512): string
    {
        if (!$this->isConfigured()) {
            return 'Le chatbot IA n\'est pas configure. Veuillez definir HF_TOKEN dans votre fichier .env.';
        }

        foreach ($this->getCandidateModels() as $model) {
            $payload = [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => 0.7,
                'stream' => false,
            ];

            for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
                try {
                    $response = $this->httpClient->request('POST', self::HF_URL, [
                        'timeout' => self::TIMEOUT,
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->hfToken,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => $payload,
                    ]);

                    $statusCode = $response->getStatusCode();
                    if ($statusCode !== 200) {
                        $responseBody = $response->getContent(false);
                        if ($this->isUnsupportedModelError($statusCode, $responseBody)) {
                            break;
                        }
                        if ($this->isRetryableStatus($statusCode) && $attempt < self::MAX_RETRIES) {
                            usleep(250000 * $attempt);
                            continue;
                        }

                        return $this->buildErrorMessage($statusCode, $responseBody);
                    }

                    $data = $response->toArray();
                    $this->workingModel = $model;

                    return $data['choices'][0]['message']['content'] ?? 'Reponse vide de l\'IA.';
                } catch (\Throwable $e) {
                    if ($attempt < self::MAX_RETRIES) {
                        usleep(250000 * $attempt);
                        continue;
                    }
                }
            }
        }

        return 'IA temporairement indisponible. Aucun modele compatible n\'est actuellement disponible pour votre configuration Hugging Face.';
    }

    private function buildErrorMessage(int $statusCode, string $responseBody): string
    {
        $apiMessage = null;
        $decoded = json_decode($responseBody, true);
        if (is_array($decoded)) {
            $apiMessage = $decoded['error']['message']
                ?? $decoded['error']
                ?? $decoded['message']
                ?? null;
        }

        if ($statusCode === 401 || $statusCode === 403) {
            return 'Authentification Hugging Face invalide. Verifiez la valeur de HF_TOKEN dans .env.';
        }

        if ($statusCode === 429) {
            return 'Quota IA atteint ou limite de requetes depassee. Reessayez plus tard.';
        }

        if ($this->isRetryableStatus($statusCode)) {
            return 'IA temporairement indisponible (code ' . $statusCode . '). Le service distant ne repond pas correctement apres plusieurs tentatives.';
        }

        if ($statusCode === 400 && is_string($apiMessage) && $apiMessage !== '') {
            return 'Requete IA invalide: ' . $apiMessage;
        }

        if (is_string($apiMessage) && $apiMessage !== '') {
            return 'IA temporairement indisponible (code ' . $statusCode . '): ' . $apiMessage;
        }

        return 'IA temporairement indisponible (code ' . $statusCode . '). Reessayez dans quelques instants.';
    }

    private function trimHistory(): void
    {
        while (count($this->conversationHistory) > self::MAX_HISTORY) {
            array_shift($this->conversationHistory);
        }
    }

    private function getCandidateModels(): array
    {
        if ($this->workingModel !== null) {
            return array_values(array_unique([$this->workingModel, ...self::HF_MODELS]));
        }

        return self::HF_MODELS;
    }

    private function isUnsupportedModelError(int $statusCode, string $responseBody): bool
    {
        if ($statusCode !== 400) {
            return false;
        }

        $apiMessage = $this->extractApiMessage($responseBody);
        if ($apiMessage === null) {
            return false;
        }

        return str_contains(strtolower($apiMessage), 'not supported by any provider you have enabled');
    }

    private function extractApiMessage(string $responseBody): ?string
    {
        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            return null;
        }

        $message = $decoded['error']['message']
            ?? $decoded['error']
            ?? $decoded['message']
            ?? null;

        return is_string($message) ? $message : null;
    }

    private function isRetryableStatus(int $statusCode): bool
    {
        return in_array($statusCode, [502, 503, 504], true);
    }
}
