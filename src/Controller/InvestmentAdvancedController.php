<?php

namespace App\Controller;

use App\Entity\InvestmentOpportunity;
use App\Entity\InvestorProfile;
use App\Entity\User;
use App\Repository\InvestmentOpportunityRepository;
use App\Repository\InvestorProfileRepository;
use App\Service\Investment\CurrencyService;
use App\Service\Investment\EconomicApiService;
use App\Service\Investment\EconomicRiskEngine;
use App\Service\Investment\InvestmentChatbotService;
use App\Service\Investment\InvestmentMatchingService;
use App\Service\Investment\MLPredictionService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/investissement/advanced')]
#[IsGranted('ROLE_USER')]
class InvestmentAdvancedController extends AbstractController
{
    // ─── Economic Dashboard ──────────────────────────────────

    #[Route('/economic-dashboard', name: 'app_invest_economic_dashboard')]
    public function economicDashboard(InvestmentOpportunityRepository $oppRepo, EconomicApiService $ecoApi): Response
    {
        $openOpportunities = $oppRepo->findOpen();
        $riskCounts = $oppRepo->countOpenByRiskBracket();
        $sectorCounts = $oppRepo->countOpenBySector();
        $shortDeadlineCount = $oppRepo->countOpenShortDeadline(6);

        // Fetch current eco data for investment climate verdict
        $ecoData = [];
        try {
            $ecoData = $ecoApi->fetchAllEconomicData('TN');
        } catch (\Throwable $e) {
            // fallback empty
        }

        $climateVerdict = $this->buildClimateVerdict($ecoData);
    $outlook = $this->build30DayOutlook($ecoData, $openOpportunities);

        // Count high-inflation-sensitive opportunities (projects in import-heavy sectors)
        $inflationSensitive = 0;
        $importSectors = ['Technologie', 'Industrie', 'Industrie textile', 'Agroalimentaire', 'Energie renouvelable'];
        foreach ($sectorCounts as $sector => $count) {
            foreach ($importSectors as $is) {
                if (stripos($sector, $is) !== false) {
                    $inflationSensitive += $count;
                    break;
                }
            }
        }

        return $this->render('front/investment/economic_dashboard.html.twig', [
            'riskCounts' => $riskCounts,
            'sectorCounts' => $sectorCounts,
            'shortDeadlineCount' => $shortDeadlineCount,
            'inflationSensitive' => $inflationSensitive,
            'climateVerdict' => $climateVerdict,
            'outlook' => $outlook,
            'ecoData' => $ecoData,
        ]);
    }

    /**
     * Build a one-sentence investment climate verdict from threshold logic.
     */
    private function buildClimateVerdict(array $ecoData): string
    {
        if (empty($ecoData)) {
            return 'Donnees economiques indisponibles. Les indicateurs ne peuvent pas etre evalues pour le moment.';
        }

        $inflation = (float) ($ecoData['inflationRate'] ?? 5.0);
        $eurUsd = (float) ($ecoData['exchangeRateEurUsd'] ?? 1.08);
        $gdp = (float) ($ecoData['gdpBillions'] ?? 50.0);
        $parts = [];

        if ($inflation >= 8.0) {
            $parts[] = 'une inflation elevee';
        } elseif ($inflation >= 5.0) {
            $parts[] = 'une inflation moderee';
        }

        if ($eurUsd < 1.02) {
            $parts[] = 'un taux de change defavorable a l\'euro';
        } elseif ($eurUsd > 1.15) {
            $parts[] = 'un euro fort';
        }

        if ($gdp < 50) {
            $parts[] = 'un PIB national modeste';
        }

        if (empty($parts)) {
            return 'Les conditions economiques actuelles sont relativement favorables aux investissements sur la plateforme.';
        }

        $joinedParts = implode(' et ', $parts);

        if ($inflation >= 8.0 || $eurUsd < 1.02) {
            return ucfirst($joinedParts) . ' creent des vents contraires pour les projets a horizon court dans les secteurs dependants des importations.';
        }

        return ucfirst($joinedParts) . ' invitent a une vigilance moderee sur les engagements a court terme.';
    }

    /**
     * @param list<InvestmentOpportunity> $opportunities
     * @return array{
     *     tone: string,
     *     toneLabel: string,
     *     headline: string,
     *     summary: string,
     *     dominantSector: string,
     *     expiringSoon: int,
     *     highRiskCount: int,
     *     signals: list<string>
     * }
     */
    private function build30DayOutlook(array $indicators, array $opportunities): array
    {
        $inflation = (float) ($indicators['inflationRate'] ?? 5.0);
        $gdp = (float) ($indicators['gdpBillions'] ?? 100.0);
        $eurUsd = (float) ($indicators['exchangeRateEurUsd'] ?? 1.08);
        $today = new \DateTimeImmutable('today');

        $sectorCounts = [];
        $highRiskCount = 0;
        $expiringSoon = 0;

        foreach ($opportunities as $opportunity) {
            $score = (float) ($opportunity->getRiskScore() ?? 50.0);
            if ($score > EconomicRiskEngine::THRESHOLD_MEDIUM) {
                $highRiskCount++;
            }

            $sector = $opportunity->getProject()?->getSecteur() ?: 'Non defini';
            $sectorCounts[$sector] = ($sectorCounts[$sector] ?? 0) + 1;

            $deadline = $opportunity->getDeadline();
            if ($deadline !== null) {
                $days = (int) $today->diff($deadline)->format('%r%a');
                if ($days >= 0 && $days <= 30) {
                    $expiringSoon++;
                }
            }
        }

        arsort($sectorCounts);
        $dominantSector = array_key_first($sectorCounts) ?? 'Aucun secteur dominant';
        $total = count($opportunities);

        $pressureScore = 0;
        if ($inflation >= 8.0) {
            $pressureScore += 2;
        } elseif ($inflation >= 5.0) {
            $pressureScore += 1;
        } else {
            $pressureScore -= 1;
        }

        if ($gdp < 100.0) {
            $pressureScore += 1;
        } elseif ($gdp >= 500.0) {
            $pressureScore -= 1;
        }

        if ($eurUsd < 1.05) {
            $pressureScore += 1;
        } elseif ($eurUsd > 1.12) {
            $pressureScore -= 1;
        }

        if ($total > 0 && $highRiskCount >= max(1, (int) ceil($total / 3))) {
            $pressureScore += 1;
        }

        if ($expiringSoon > 0) {
            $pressureScore += 1;
        }

        if ($pressureScore >= 3) {
            $tone = 'defensive';
            $toneLabel = 'Defensif';
            $headline = 'Perspectives 30 jours: execution sous tension';
        } elseif ($pressureScore >= 1) {
            $tone = 'selective';
            $toneLabel = 'Selectif';
            $headline = 'Perspectives 30 jours: fenetre selective';
        } else {
            $tone = 'constructive';
            $toneLabel = 'Constructif';
            $headline = 'Perspectives 30 jours: biais constructif';
        }

        $summary = sprintf(
            'Sur les 30 prochains jours, le contexte combine %.1f%% d inflation, un EUR/USD a %.2f et %d opportunite%s ouverte%s, avec %d dossier%s a risque eleve et %d echeance%s courte%s.',
            $inflation,
            $eurUsd,
            $total,
            $total > 1 ? 's' : '',
            $total > 1 ? 's' : '',
            $highRiskCount,
            $highRiskCount > 1 ? 's' : '',
            $expiringSoon,
            $expiringSoon > 1 ? 's' : '',
            $expiringSoon > 1 ? 's' : ''
        );

        $signals = [
            $inflation >= 8.0
                ? 'Le signal macro reste tendu: l inflation elevee plaide pour des tickets mieux securises et des hypotheses de marge prudentes.'
                : 'Le signal macro reste relativement maitrisable: l inflation ne detruit pas a elle seule la these d investissement a court terme.',
            $expiringSoon > 0
                ? sprintf('%d opportunite%s demande%s une decision rapide sous 30 jours, ce qui augmente la valeur d une verification serree des jalons et de la tresorerie.', $expiringSoon, $expiringSoon > 1 ? 's' : '', $expiringSoon > 1 ? 'nt' : '')
                : 'Aucune opportunite ouverte n arrive a echeance immediate, ce qui laisse davantage de marge pour comparer les dossiers.',
            sprintf('Le secteur le plus expose dans le pipeline actuel est %s, a surveiller en priorite pour capter la concentration du risque et des opportunites.', $dominantSector),
        ];

        return [
            'tone' => $tone,
            'toneLabel' => $toneLabel,
            'headline' => $headline,
            'summary' => $summary,
            'dominantSector' => $dominantSector,
            'expiringSoon' => $expiringSoon,
            'highRiskCount' => $highRiskCount,
            'signals' => $signals,
        ];
    }

    #[Route('/economic-data', name: 'app_invest_economic_data', methods: ['GET'])]
    public function economicData(
        Request $request,
        EconomicApiService $api,
        EconomicRiskEngine $engine,
    ): JsonResponse {
        $country = $request->query->get('country', 'TN');
        $allowed = array_keys($api->getSupportedCountries());
        if (!in_array(strtoupper($country), $allowed, true)) {
            $country = 'TN';
        }

        $data = $api->fetchAllEconomicData($country);
        $economicFactor = $engine->computeEconomicFactor($data);

        $data['economicRiskFactor'] = round($economicFactor, 1);
        $data['riskLevel'] = EconomicRiskEngine::getRiskLevel((int) $economicFactor);
        $data['riskColor'] = EconomicRiskEngine::getRiskColor((int) $economicFactor);
        $data['recommendation'] = EconomicRiskEngine::getRecommendation((int) $economicFactor);

        return $this->json($data);
    }

    // ─── Currency Converter ──────────────────────────────────

    #[Route('/currency-convert', name: 'app_invest_currency_convert', methods: ['GET'])]
    public function currencyConvert(Request $request, CurrencyService $currency): JsonResponse
    {
        $currency->fetchRates();

        $amount = (float) $request->query->get('amount', 1);
        $from = strtoupper($request->query->get('from', 'EUR'));
        $to = strtoupper($request->query->get('to', 'TND'));

        if (!in_array($from, CurrencyService::CURRENCIES, true)) $from = 'EUR';
        if (!in_array($to, CurrencyService::CURRENCIES, true)) $to = 'TND';

        $converted = $currency->convert($amount, $from, $to);

        return $this->json([
            'amount' => $amount,
            'from' => $from,
            'to' => $to,
            'converted' => round($converted, 4),
            'formatted' => CurrencyService::format($converted, $to),
            'rate' => round($currency->getRate($from, $to), 6),
            'rates' => $currency->getRates(),
        ]);
    }

    // ─── Risk Analysis ───────────────────────────────────────

    #[Route('/risk-analysis/{id}', name: 'app_invest_risk_analysis', requirements: ['id' => '\d+'])]
    public function riskAnalysis(InvestmentOpportunity $opp): Response
    {
        return $this->render('front/investment/risk_analysis.html.twig', [
            'opportunity' => $opp,
        ]);
    }

    public function brief(
        InvestmentOpportunity $opp,
        Request $request,
        InvestorProfileRepository $profileRepo,
        InvestmentMatchingService $matchingService,
        InvestmentChatbotService $chatbot,
        EconomicApiService $api,
        EconomicRiskEngine $engine,
    ): Response {
        if (!$this->isGranted('ROLE_INVESTISSEUR')) {
            throw $this->createAccessDeniedException('Cette fonctionnalite est reservee aux investisseurs.');
        }

        $user = $this->requireUser();
        $country = strtoupper((string) $request->query->get('country', 'TN'));
        $allowedCountries = array_keys($api->getSupportedCountries());
        if (!in_array($country, $allowedCountries, true)) {
            $country = 'TN';
        }

        try {
            $economicData = $api->fetchAllEconomicData($country);
        } catch (\Throwable $exception) {
            $economicData = [
                'dataAvailable' => false,
                'country' => $country,
                'countryName' => $api->getCountryName($country),
                'exchangeRateEurUsd' => 1.08,
                'gdpBillions' => 0.0,
                'inflationRate' => 5.0,
            ];
        }

        $targetAmount = (float) $opp->getTargetAmount();
        $riskScore = $engine->calculateFullRisk($targetAmount, $opp->getDeadline(), $economicData);
        $riskLevel = EconomicRiskEngine::getRiskLevel($riskScore);
        $riskFactors = [
            'amount' => round($engine->normalizeAmount($targetAmount), 1),
            'duration' => round($engine->normalizeDuration($opp->getDeadline()), 1),
            'economic' => round($engine->computeEconomicFactor($economicData), 1),
        ];

        $profile = $profileRepo->findByUser($user);
        $matchData = [
            'matched' => false,
            'score' => null,
            'explanation' => null,
        ];

        if ($profile !== null) {
            foreach ($matchingService->findMatches($profile) as $match) {
                if ($match['opportunity']->getId() === $opp->getId()) {
                    $matchData = [
                        'matched' => true,
                        'score' => $match['score'],
                        'explanation' => $match['explanation'],
                    ];
                    break;
                }
            }
        }

        $context = [
            'mode' => 'risk',
            'opportunityTitle' => $opp->getProject()?->getTitre() ?? 'Projet',
            'sector' => $opp->getProject()?->getSecteur() ?? 'N/A',
            'fundingTarget' => number_format($targetAmount, 0, ',', ' '),
            'deadline' => $opp->getDeadline()?->format('d/m/Y') ?? 'N/A',
            'riskScore' => (string) $riskScore,
            'riskLevel' => $riskLevel,
            'inflationRate' => (string) ($economicData['inflationRate'] ?? 'N/A'),
            'gdpGrowth' => (string) ($economicData['gdpBillions'] ?? 'N/A'),
            'exchangeRate' => (string) ($economicData['exchangeRateEurUsd'] ?? 'N/A'),
            'investorBudgetMin' => $profile?->getBudgetMin() ?? 'N/A',
            'investorBudgetMax' => $profile?->getBudgetMax() ?? 'N/A',
            'investorPreferredSectors' => $profile?->getPreferredSectors() ?? 'N/A',
            'investorRiskTolerance' => $profile !== null ? (string) $profile->getRiskTolerance() : 'N/A',
            'customInstruction' => 'Write a professional investment brief in French in exactly three distinct paragraphs. Paragraph 1 must summarize the opportunity and its sector context. Paragraph 2 must assess the risk in a way personalized to the investor profile, budget, sectors, horizon, and matching fit. Paragraph 3 must provide a recommendation with specific reasoning. Do not use bullet points, titles, salutations, or legal disclaimers. Plain professional prose only.',
        ];

        $briefPrompt = sprintf(
            "Redige un brief d'investissement professionnel en francais pour l'opportunite \"%s\". "
            . "Base-toi sur le score de risque %d/100 (%s), le secteur %s, le montant cible de %.0f DT, la date limite %s, et la description suivante : %s. "
            . "Profil investisseur : secteurs preferes %s, tolerance au risque %s/10, budget %s a %s DT, horizon %s mois. "
            . "Compatibilite avec le matching : %s%s.",
            $opp->getProject()?->getTitre() ?? 'Projet',
            $riskScore,
            $riskLevel,
            $opp->getProject()?->getSecteur() ?? 'N/A',
            $targetAmount,
            $opp->getDeadline()?->format('d/m/Y') ?? 'N/A',
            trim((string) ($opp->getDescription() ?? 'Description non renseignee')),
            $profile?->getPreferredSectors() ?? 'aucune preference renseignee',
            $profile?->getRiskTolerance() ?? 5,
            $profile?->getBudgetMin() ?? '0',
            $profile?->getBudgetMax() ?? 'N/A',
            $profile?->getHorizonMonths() ?? 12,
            $matchData['matched'] ? 'oui' : 'non',
            $matchData['explanation'] ? ' (' . $matchData['explanation'] . ')' : ''
        );

        $briefText = null;
        if ($chatbot->isConfigured()) {
            try {
                $candidate = $chatbot->chatWithContext($briefPrompt, $context, []);
                if (!$chatbot->isFailureResponse($candidate)) {
                    $briefText = $candidate;
                }
            } catch (\Throwable $exception) {
                $briefText = null;
            }
        }

        $fallbackParagraphs = $this->buildDeterministicBriefParagraphs($opp, $profile, $riskScore, $riskLevel, $riskFactors, $economicData, $matchData);
        $briefParagraphs = $this->normalizeBriefParagraphs($briefText, $fallbackParagraphs);

        $html = $this->renderView('front/investment/investment_brief.html.twig', [
            'opportunity' => $opp,
            'investorDisplayName' => $this->buildInvestorDisplayName($user),
            'generatedAt' => new \DateTimeImmutable(),
            'briefParagraphs' => $briefParagraphs,
            'riskScore' => $riskScore,
            'riskLevel' => $riskLevel,
            'riskFactors' => $riskFactors,
            'economicData' => $economicData,
            'profile' => $profile,
            'matchData' => $matchData,
            'equityMetric' => $this->resolveOpportunityEquity($opp),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $safeProject = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($opp->getProject()?->getTitre() ?? 'projet'));
        $filename = sprintf('brief-investissement-%s-%s.pdf', trim((string) $safeProject, '-'), (new \DateTimeImmutable())->format('Y-m-d'));

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/risk-compute/{id}', name: 'app_invest_risk_compute', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function riskCompute(
        InvestmentOpportunity $opp,
        Request $request,
        EconomicApiService $api,
        EconomicRiskEngine $engine,
        MLPredictionService $mlPredictionService,
    ): JsonResponse {
        $country = $request->query->get('country', 'TN');
        $data = $api->fetchAllEconomicData($country);

        $targetAmount = (float) $opp->getTargetAmount();
        $deadline = $opp->getDeadline();

        $score = $engine->calculateFullRisk($targetAmount, $deadline, $data);
        $economicFactor = $engine->computeEconomicFactor($data);

        $payload = [
            'score' => $score,
            'level' => EconomicRiskEngine::getRiskLevel($score),
            'color' => EconomicRiskEngine::getRiskColor($score),
            'recommendation' => EconomicRiskEngine::getRecommendation($score),
            'factors' => [
                'amount' => round($engine->normalizeAmount($targetAmount), 1),
                'duration' => round($engine->normalizeDuration($deadline), 1),
                'economic' => round($economicFactor, 1),
            ],
            'economicData' => [
                'country' => $data['countryName'] ?? $country,
                'eurUsd' => round($data['exchangeRateEurUsd'] ?? 0, 4),
                'eurTnd' => round($data['exchangeRateEurTnd'] ?? 0, 3),
                'gdp' => round($data['gdpBillions'] ?? 0, 1),
                'inflation' => round($data['inflationRate'] ?? 0, 1),
                'year' => $data['dataYear'] ?? 'N/A',
            ],
        ];

        $mlPrediction = $mlPredictionService->predictSuccessProbability($opp, $economicFactor, $opp->getOffers()->count());
        if ($mlPrediction !== null) {
            $payload['mlPrediction'] = [
                'probability' => round(max(0.0, min(1.0, (float) $mlPrediction['success_probability'])), 4),
                'confidence' => (string) ($mlPrediction['confidence'] ?? 'low'),
                'synthetic' => (bool) ($mlPrediction['synthetic_data'] ?? false),
            ];
        }

        return $this->json($payload);
    }

    // ─── AI Matching ─────────────────────────────────────────

    #[Route('/matching', name: 'app_invest_matching')]
    #[IsGranted('ROLE_INVESTISSEUR')]
    public function matching(InvestorProfileRepository $profileRepo): Response
    {
        $profile = $profileRepo->findByUser($this->requireUser());
        $profileAdapted = $profile !== null
            && $profile->getUpdatedAt()->getTimestamp() >= (new \DateTimeImmutable('-48 hours'))->getTimestamp();

        return $this->render('front/investment/matching.html.twig', [
            'profile' => $profile,
            'profileAdapted' => $profileAdapted,
        ]);
    }

    #[Route('/matching/results', name: 'app_invest_matching_results', methods: ['GET'])]
    #[IsGranted('ROLE_INVESTISSEUR')]
    public function matchingResults(
        InvestorProfileRepository $profileRepo,
        InvestmentMatchingService $matchingService,
    ): JsonResponse {
        $profile = $profileRepo->findByUser($this->requireUser());
        if ($profile === null) {
            return $this->json(['error' => 'Creez votre profil investisseur d\'abord.', 'matches' => []], 400);
        }

        $matches = $matchingService->findMatches($profile);

        $data = [];
        foreach ($matches as $match) {
            $opp = $match['opportunity'];
            $data[] = [
                'id' => $opp->getId(),
                'projectTitle' => $opp->getProject()?->getTitre() ?? 'Projet',
                'sector' => $opp->getProject()?->getSecteur() ?? 'N/A',
                'targetAmount' => number_format((float) $opp->getTargetAmount(), 0, ',', ' '),
                'deadline' => $opp->getDeadline()?->format('d/m/Y'),
                'riskScore' => $opp->getRiskScore(),
                'compatibilityScore' => $match['score'],
                'explanation' => $match['explanation'],
                'showUrl' => $this->generateUrl('app_invest_opportunity_show', ['id' => $opp->getId()]),
                'riskUrl' => $this->generateUrl('app_invest_risk_analysis', ['id' => $opp->getId()]),
            ];
        }

        return $this->json(['matches' => $data]);
    }

    #[Route('/profile/save', name: 'app_invest_profile_save', methods: ['POST'])]
    #[IsGranted('ROLE_INVESTISSEUR')]
    public function saveProfile(
        Request $request,
        InvestorProfileRepository $profileRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->requireUser();
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('investor_profile', $token)) {
            return $this->json(['error' => 'Jeton de securite invalide.'], 403);
        }

        $profile = $profileRepo->findByUser($user);
        if ($profile === null) {
            $profile = new InvestorProfile();
            $profile->setUser($user);
        }

        $riskTolerance = max(1, min(10, (int) $request->request->get('riskTolerance', 5)));
        $budgetMin = max(0, (float) $request->request->get('budgetMin', 0));
        $budgetMax = max(0, (float) $request->request->get('budgetMax', 10000000));
        $horizonMonths = max(1, (int) $request->request->get('horizonMonths', 12));

        if ($budgetMin >= $budgetMax) {
            return $this->json([
                'error' => 'Le budget minimum doit etre strictement inferieur au budget maximum.',
            ], 400);
        }

        $profile->setPreferredSectors($request->request->get('sectors', ''));
        $profile->setRiskTolerance($riskTolerance);
        $profile->setBudgetMin((string) $budgetMin);
        $profile->setBudgetMax((string) $budgetMax);
        $profile->setHorizonMonths($horizonMonths);
        $profile->setDescription($request->request->get('description'));

        $em->persist($profile);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Profil enregistre avec succes.']);
    }

    // ─── Chatbot ─────────────────────────────────────────────

    #[Route('/chatbot', name: 'app_invest_chatbot', methods: ['POST'])]
    public function chatbot(
        Request $request,
        InvestmentChatbotService $chatbot,
        InvestmentOpportunityRepository $oppRepo,
        InvestorProfileRepository $profileRepo,
    ): JsonResponse {
        $message = trim($request->request->get('message', ''));
        if ($message === '') {
            return $this->json(['response' => null, 'error' => 'Message vide.'], 400);
        }

        if (mb_strlen($message) > 2000) {
            return $this->json(['response' => null, 'error' => 'Message trop long (max 2000 caracteres).'], 400);
        }

        // Decode conversation history
        $historyRaw = $request->request->get('conversationHistory', '[]');
        $conversationHistory = json_decode($historyRaw, true);
        if (!is_array($conversationHistory)) {
            $conversationHistory = [];
        }

        // Build context from POST params
        $context = [
            'mode' => 'risk',
            'opportunityTitle' => $request->request->get('opportunityTitle', 'N/A'),
            'sector' => $request->request->get('sector', 'N/A'),
            'fundingTarget' => $request->request->get('fundingTarget', 'N/A'),
            'deadline' => $request->request->get('deadline', 'N/A'),
            'riskScore' => $request->request->get('riskScore', 'N/A'),
            'riskLevel' => $request->request->get('riskLevel', 'N/A'),
            'inflationRate' => $request->request->get('inflationRate', 'N/A'),
            'gdpGrowth' => $request->request->get('gdpGrowth', 'N/A'),
            'exchangeRate' => $request->request->get('exchangeRate', 'N/A'),
            'investorBudgetMin' => $request->request->get('investorBudgetMin', 'N/A'),
            'investorBudgetMax' => $request->request->get('investorBudgetMax', 'N/A'),
            'investorPreferredSectors' => $request->request->get('investorPreferredSectors', 'N/A'),
            'investorRiskTolerance' => $request->request->get('investorRiskTolerance', 'N/A'),
        ];

        // Enrich context from DB if opportunityId provided
        $oppId = (int) $request->request->get('opportunityId', 0);
        if ($oppId > 0) {
            $opp = $oppRepo->find($oppId);
            if ($opp) {
                if ($context['opportunityTitle'] === 'N/A') {
                    $context['opportunityTitle'] = $opp->getProject()?->getTitre() ?? 'N/A';
                }
                if ($context['sector'] === 'N/A') {
                    $context['sector'] = $opp->getProject()?->getSecteur() ?? 'N/A';
                }
                if ($context['fundingTarget'] === 'N/A') {
                    $context['fundingTarget'] = (string) $opp->getTargetAmount();
                }
                if ($context['deadline'] === 'N/A') {
                    $context['deadline'] = $opp->getDeadline()?->format('d/m/Y') ?? 'N/A';
                }
            }
        }

        // Enrich investor profile from DB
        $profile = $profileRepo->findByUser($this->requireUser());
        if ($profile) {
            if ($context['investorBudgetMin'] === 'N/A') {
                $context['investorBudgetMin'] = $profile->getBudgetMin();
            }
            if ($context['investorBudgetMax'] === 'N/A') {
                $context['investorBudgetMax'] = $profile->getBudgetMax();
            }
            if ($context['investorPreferredSectors'] === 'N/A') {
                $context['investorPreferredSectors'] = $profile->getPreferredSectors() ?? 'N/A';
            }
            if ($context['investorRiskTolerance'] === 'N/A') {
                $context['investorRiskTolerance'] = (string) $profile->getRiskTolerance();
            }
        }

        try {
            $response = $chatbot->chatWithContext($message, $context, $conversationHistory);
            return $this->json(['response' => $response, 'error' => null]);
        } catch (\Throwable $e) {
            return $this->json(['response' => null, 'error' => 'Erreur du service IA.'], 500);
        }
    }

    #[Route('/chatbot/analyze-risk', name: 'app_invest_chatbot_risk', methods: ['POST'])]
    public function chatbotRiskAnalysis(
        Request $request,
        InvestmentChatbotService $chatbot,
        InvestmentOpportunityRepository $oppRepo,
    ): JsonResponse {
        $oppId = (int) $request->request->get('opportunityId', 0);
        $opp = $oppRepo->find($oppId);
        if ($opp === null) {
            return $this->json(['error' => 'Opportunite introuvable.'], 404);
        }

        $response = $chatbot->analyzeRisk(
            $opp->getProject()?->getTitre() ?? 'Projet',
            $opp->getProject()?->getSecteur() ?? 'N/A',
            (float) $opp->getTargetAmount(),
            $opp->getDeadline()?->format('d/m/Y') ?? 'N/A',
            $opp->getDescription() ?? '',
            $opp->getRiskScore() ?? 50.0,
        );

        return $this->json(['response' => $response]);
    }

    #[Route('/risk-verdict/{id}', name: 'app_invest_risk_verdict', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function riskVerdict(
        InvestmentOpportunity $opp,
        Request $request,
        InvestmentChatbotService $chatbot,
        EconomicApiService $api,
        EconomicRiskEngine $engine,
    ): JsonResponse {
        $country = $request->query->get('country', 'TN');
        $data = $api->fetchAllEconomicData($country);
        $riskScore = (float) ($opp->getRiskScore() ?? $engine->calculateFullRisk(
            (float) $opp->getTargetAmount(),
            $opp->getDeadline(),
            $data,
        ));
        $riskLevel = EconomicRiskEngine::getRiskLevel((int) $riskScore);

        $deterministicVerdict = $engine->buildDeterministicVerdict(
            (int) round($riskScore),
            $data,
            (float) $opp->getTargetAmount(),
            $opp->getDeadline(),
        );

        $aiAnalysis = null;
        if ($chatbot->isConfigured()) {
            $candidate = $chatbot->analyzeRisk(
                $opp->getProject()?->getTitre() ?? 'Projet',
                $opp->getProject()?->getSecteur() ?? 'N/A',
                (float) $opp->getTargetAmount(),
                $opp->getDeadline()?->format('d/m/Y') ?? 'N/A',
                ($opp->getDescription() ?? '') . sprintf(
                    "\n\nContexte economique: pays %s, inflation %.1f%%, PIB %.1f Mrd $, EUR/USD %.4f.",
                    $data['countryName'] ?? $country,
                    (float) ($data['inflationRate'] ?? 0),
                    (float) ($data['gdpBillions'] ?? 0),
                    (float) ($data['exchangeRateEurUsd'] ?? 0),
                ),
                $riskScore,
            );

            if (!$chatbot->isFailureResponse($candidate)) {
                $aiAnalysis = $candidate;
            }
        }

        return $this->json([
            'verdict' => $deterministicVerdict,
            'aiAnalysis' => $aiAnalysis,
            'riskLevel' => $riskLevel,
            'configured' => $chatbot->isConfigured(),
        ]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        return $user;
    }

    private function buildDeterministicBriefParagraphs(
        InvestmentOpportunity $opp,
        ?InvestorProfile $profile,
        int $riskScore,
        string $riskLevel,
        array $riskFactors,
        array $economicData,
        array $matchData,
    ): array {
        $title = $opp->getProject()?->getTitre() ?? 'ce projet';
        $sector = $opp->getProject()?->getSecteur() ?? 'un secteur non precise';
        $deadline = $opp->getDeadline()?->format('d/m/Y') ?? 'une date non definie';
        $country = (string) ($economicData['countryName'] ?? $economicData['country'] ?? 'ce marche');
        $inflation = number_format((float) ($economicData['inflationRate'] ?? 0), 1, ',', ' ');
        $gdp = number_format((float) ($economicData['gdpBillions'] ?? 0), 1, ',', ' ');
        $exchange = number_format((float) ($economicData['exchangeRateEurUsd'] ?? 0), 4, ',', ' ');

        $paragraphOne = sprintf(
            "%s cherche un financement de %s DT dans le secteur %s, avec une echeance fixee au %s. Dans le contexte economique actuel de %s, marque par une inflation de %s%%, un PIB de %s milliards USD et un taux EUR/USD de %s, l'opportunite se situe dans un environnement qui demande une lecture attentive de l'execution et du calendrier.",
            $title,
            number_format((float) $opp->getTargetAmount(), 0, ',', ' '),
            $sector,
            $deadline,
            $country,
            $inflation,
            $gdp,
            $exchange
        );

        if ($profile !== null) {
            $paragraphTwo = sprintf(
                "Pour votre profil investisseur, le risque ressort a %d/100 (%s), avec une pression principale provenant du facteur economique (%s/100), puis du montant (%s/100) et de la duree (%s/100). Votre budget cible se situe entre %s et %s DT, votre tolerance actuelle est de %d/10 et vos secteurs preferes sont %s ; cette opportunite est %s%s.",
                $riskScore,
                $riskLevel,
                number_format((float) $riskFactors['economic'], 1, ',', ' '),
                number_format((float) $riskFactors['amount'], 1, ',', ' '),
                number_format((float) $riskFactors['duration'], 1, ',', ' '),
                number_format((float) $profile->getBudgetMin(), 0, ',', ' '),
                number_format((float) $profile->getBudgetMax(), 0, ',', ' '),
                $profile->getRiskTolerance(),
                $profile->getPreferredSectors() ?: 'non renseignes',
                $matchData['matched'] ? 'compatible' : 'moins bien alignee',
                $matchData['explanation'] ? ' selon le moteur de matching (' . $matchData['explanation'] . ')' : ''
            );
        } else {
            $paragraphTwo = sprintf(
                "Sans profil investisseur detaille, l'evaluation se concentre sur le score de risque global de %d/100 (%s), tire principalement par l'economie (%s/100), puis par le montant (%s/100) et la duree (%s/100). Cette lecture reste utile pour situer l'opportunite, mais elle gagne en precision lorsqu'elle est comparee a votre budget, votre horizon et votre tolerance au risque personnels.",
                $riskScore,
                $riskLevel,
                number_format((float) $riskFactors['economic'], 1, ',', ' '),
                number_format((float) $riskFactors['amount'], 1, ',', ' '),
                number_format((float) $riskFactors['duration'], 1, ',', ' ')
            );
        }

        $recommendation = $riskScore <= 35
            ? 'L opportunite peut etre consideree comme defendable pour un investisseur prudent a condition de suivre les jalons et les reportings avec regularite.'
            : ($riskScore <= 66
                ? 'Le dossier merite une approche selective, avec verification du calendrier, des livrables et des hypotheses economiques avant engagement.'
                : 'Le dossier appelle a une forte prudence et convient surtout a un investisseur capable d assumer une execution plus incertaine et un suivi resserre.');

        $paragraphThree = sprintf(
            "%s La recommandation pratique est de confronter ce niveau de risque a votre capacite de diversification, puis de verifier en priorite la solidite des jalons, la credibilite du besoin de financement et la resilience du projet face au contexte macroeconomique actuel.",
            $recommendation
        );

        return [$paragraphOne, $paragraphTwo, $paragraphThree];
    }

    /**
     * @param list<string> $fallbackParagraphs
     * @return list<string>
     */
    private function normalizeBriefParagraphs(?string $briefText, array $fallbackParagraphs): array
    {
        if ($briefText === null || trim($briefText) === '') {
            return $fallbackParagraphs;
        }

        $paragraphs = array_values(array_filter(array_map(
            static fn (string $paragraph): string => trim(preg_replace('/\s+/', ' ', $paragraph) ?? ''),
            preg_split('/\n\s*\n+/', str_replace("\r", '', trim($briefText))) ?: []
        )));

        return count($paragraphs) >= 3 ? array_slice($paragraphs, 0, 3) : $fallbackParagraphs;
    }

    private function buildInvestorDisplayName(User $user): string
    {
        $firstname = trim((string) ($user->getFirstname() ?? 'Investisseur'));
        $lastname = trim((string) ($user->getLastname() ?? ''));
        $initial = $lastname !== '' ? strtoupper(substr($lastname, 0, 1)) . '.' : '';

        return trim($firstname . ' ' . $initial);
    }

    private function resolveOpportunityEquity(InvestmentOpportunity $opp): ?string
    {
        foreach ($opp->getOffers() as $offer) {
            $equity = $offer->getContract()?->getEquityPercentage();
            if ($equity !== null && $equity !== '') {
                return $equity;
            }
        }

        return null;
    }
}
