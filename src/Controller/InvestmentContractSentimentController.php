<?php

namespace App\Controller;

use App\Entity\InvestmentContract;
use App\Entity\User;
use App\Repository\InvestmentContractMessageRepository;
use App\Service\Investment\NegotiationSentimentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/investissement/contrat')]
#[IsGranted('ROLE_USER')]
class InvestmentContractSentimentController extends AbstractController
{
    #[Route('/{id}/sentiment', name: 'app_invest_contract_sentiment', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function __invoke(
        InvestmentContract $contract,
        InvestmentContractMessageRepository $messageRepository,
        NegotiationSentimentService $sentimentService,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        if (!$contract->belongsTo($user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas acces a ce contrat.');
        }

        $messages = $messageRepository->findLatestConversationMessages($contract, 10);

        return $this->json($sentimentService->analyze($contract, $messages));
    }
}