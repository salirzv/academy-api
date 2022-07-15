<?php

namespace App\Controller\Panel\Transaction;

use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Morilog\Jalali\Jalalian;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Routing\Annotation\Route;

class PanelTransactionController extends AbstractController
{
    /**
     * @Route("/dashboard/gettransactions", name="dashboardGetTransactions", methods={"POST"})
     */
    public function dashboardGetTransactions(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        $results = [];
        $transactions = $entityManager->getRepository(Transaction::class)->findBy([
            'owner' => $this->getUser()
        ], ['id' => 'DESC']);
        foreach ($transactions as $key => $transaction){
            $results[$key]['trs_id'] = $transaction->getTrsId();
            $results[$key]['created_at'] = Jalalian::fromDateTime($transaction->getCreatedAt())->format('Y/n/j - G:i');
            $results[$key]['amount'] = $transaction->getArOrder()->getAmount();
        }

        return new JsonResponse($serializer->serialize($results, 'json'));
    }

}