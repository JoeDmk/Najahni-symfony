<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\InvestmentContractRepository;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class InvestmentContractVerifyController extends AbstractController
{
    #[Route('/investissement/contrat/{contractId}/verifier', name: 'app_invest_contract_verify', requirements: ['contractId' => '\d+'], methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function verify(
        int $contractId,
        InvestmentContractRepository $contractRepo,
    ): Response {
        $contract = $contractRepo->find($contractId);

        $valid = $contract !== null && $contract->isFullySigned();

        return $this->render('front/investment/contract_verify.html.twig', [
            'contract' => $contract,
            'valid' => $valid,
        ]);
    }

    #[Route('/investissement/contrat/{contractId}/qr', name: 'app_invest_contract_qr', requirements: ['contractId' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function qrCode(
        int $contractId,
        InvestmentContractRepository $contractRepo,
    ): Response {
        $contract = $contractRepo->find($contractId);
        if (!$contract) {
            throw $this->createNotFoundException('Contrat introuvable.');
        }

        $user = $this->getUser();
        if (!$user instanceof User || !$contract->belongsTo($user)) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $verifyUrl = $this->generateUrl('app_invest_contract_verify', ['contractId' => $contract->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $result = (new Builder(
            writer: new PngWriter(),
            data: $verifyUrl,
            encoding: new Encoding('UTF-8'),
            size: 300,
            margin: 10,
        ))->build();

        return new Response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
        ]);
    }
}
