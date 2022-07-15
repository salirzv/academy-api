<?php

namespace App\Controller\Landing\Cart;

use App\Entity\Course;
use App\Entity\Order;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use DateTime;

class LandingCartController extends AbstractController
{
    /**
     * @Route("/cart/getitems", name="landingGetCartItems", methods={"POST"})
     */
    public function landingGetCartItems(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        $items = json_decode($request->request->get('items'));
        $items_info = [];
        foreach ($items as $item) {
            $items_info[] = $entityManager->getRepository(Course::class)->find($item);
        }
        return new JsonResponse($serializer->serialize($items_info, 'json', [
            'ignored_attributes' => ['categories']
        ]));
    }

    /**
     * @Route("/cart/createorder", name="landingCreateOrder", methods={"POST"})
     */
    public function landingCreateOrder(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        $response['hasError'] = true;

        try {
            $pre_order = $entityManager->createQueryBuilder()
                ->select('o')
                ->from('App:Order', 'o')
                ->where('o.owner = :owner')
                ->andWhere('o.status = :status')
                ->setParameters([
                    'owner' => $this->getUser(),
                    'status' => "pending-payment"
                ])->getQuery()->getSingleResult();
        } catch (NoResultException | NonUniqueResultException $e) {
            $pre_order = [];
        }

        if (!empty($pre_order)) {
            $response['errorMessage'] = 'یک سفارش در انتظار پرداخت براش شما ثبت شده است، قبل از ثبت سفارش جدید وضعیت سفارش قبلی را مشخص کنید';
            return new JsonResponse(json_encode($response));
        }

        $items = json_decode($request->request->get('items'));

        if (empty($items)) {
            $response['errorMessage'] = 'سبد خرید خالی می باشد';
            return new JsonResponse(json_encode($response));
        }

        $courses = [];
        foreach ($items as $item) {
            $course = $entityManager->getRepository(Course::class)->find($item);
            if (empty($course)) {
                throw new BadRequestException();
            }
            $pre_courses = $entityManager->createQueryBuilder()
                ->select('o')->from('App:Order', 'o')
                ->innerJoin('o.items', 'c')
                ->where('c = :course')
                ->andWhere('o.owner = :owner')->setParameters([
                    'course' => $course,
                    'owner' => $this->getUser()
                ])->getQuery()->execute();
            foreach ($pre_courses as $pre_course) {
                if ($pre_course->getStatus() === 'paid') {
                    $response['errorMessage'] = sprintf('دوره %s قبلا خریداری شده است', $course->getTitle());
                    return new JsonResponse(json_encode($response));
                }
            }
            $courses[] = $course;
        }


        $total_price = 0;
        foreach ($courses as $course) {
            $total_price += intval($course->getPrice());
        }

        $order = (new Order())
            ->setOwner($this->getUser())
            ->setCreatedAt(new DateTime())
            ->setStatus('pending-payment')
            ->setAmount(strval($total_price));

        foreach ($courses as $course) {
            $order->addItem($course);
        }

        $entityManager->persist($order);
        $entityManager->flush();


        function send($api, $amount, $redirect)
        {
            return curl_post('https://pay.ir/pg/send', [
                'api' => $api,
                'amount' => $amount,
                'redirect' => $redirect,
            ]);
        }

        function verify($api, $token)
        {
            return curl_post('https://pay.ir/pg/verify', [
                'api' => $api,
                'token' => $token,
            ]);
        }

        function curl_post($url, $params)
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            $res = curl_exec($ch);
            curl_close($ch);

            return $res;
        }

        $id = $order->getId();
        $result = send('test', $total_price * 10, "https://localhost:8000/api/verifyorder?o=$id");
        $result = json_decode($result);

        if ($result->status) {
            $go = "https://pay.ir/pg/$result->token";
            $response['hasError'] = false;
            $response['redirect'] = $go;
        } else {
            $response['errorMessage'] = $result->errorMessage;
        }
        return new JsonResponse(json_encode($response));
    }

    /**
     * @Route("/verifyorder", name="landingVerifyOrder", methods={"GET"})
     */
    public function landingVerifyOrder(Request $request, EntityManagerInterface $entityManager): Response
    {
        $o_id = $request->query->get('o');
        $status = $request->query->get('status');
        $token = $request->query->get('token');

        function verify($api, $token)
        {
            return curl_post('https://pay.ir/pg/verify', [
                'api' => $api,
                'token' => $token,
            ]);
        }

        function curl_post($url, $params)
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            $res = curl_exec($ch);
            curl_close($ch);

            return $res;
        }

        if ($status == 1) {
            $result = json_decode(verify('test', $token));
            $order = $entityManager->getRepository(Order::class)->find($o_id);
            $trsID = strval($result->transId);
            $pre_transaction = $entityManager->getRepository(Transaction::class)->findOneBy(['trs_id' => $trsID]);
            if (!empty($pre_transaction)) {
                throw new BadRequestException();
            }
            if (intval($order->getAmount()) !== intval($result->amount) / 10) {
                throw new BadRequestException();
            }
            $transaction = (new Transaction())->setOwner($order->getOwner())->setCreatedAt(new DateTime())->setArOrder($order)->setTrsId($trsID);
            $order->setTransaction($transaction)->setStatus('paid');
            $entityManager->persist($transaction);
            $entityManager->persist($order);
            $entityManager->flush();
            return new RedirectResponse('http://academy.test/checkout/success');
        } else {
            return new RedirectResponse('http://academy.test/checkout/fail');
        }
    }

    /**
     * @Route("/cart/getpendingorder", name="landingGetPendingOrder", methods={"POST"})
     */
    public function landingGetPendingOrder(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        try {
            $result = $entityManager->createQueryBuilder()
                ->select('o')
                ->from('App:Order', 'o')
                ->where('o.owner = :owner')
                ->andWhere('o.status = :status')
                ->setParameters([
                    'owner' => $this->getUser(),
                    'status' => "pending-payment"
                ])->getQuery()->getSingleResult();
        } catch (NoResultException | NonUniqueResultException $e) {
            $result = [];
        }

        return new JsonResponse($serializer->serialize($result, 'json', [
            'ignored_attributes' => ['owner', 'transaction', 'createdAt', 'categories']
        ]));
    }

    /**
     * @Route("/cart/payorder", name="landingPayOrder", methods={"POST"})
     */
    public function landingPayOrder(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        $response['hasError'] = true;

        try {
            $order = $entityManager->createQueryBuilder()
                ->select('o')
                ->from('App:Order', 'o')
                ->where('o.owner = :owner')
                ->andWhere('o.status = :status')
                ->setParameters([
                    'owner' => $this->getUser(),
                    'status' => "pending-payment"
                ])->getQuery()->getSingleResult();
        } catch (NoResultException | NonUniqueResultException $e) {
            $order = [];
        }

        if (empty($order)) {
            $response['errorMessage'] = 'سفارشی پیدا نشد';
            return new JsonResponse(json_encode($response));
        }

        function send($api, $amount, $redirect)
        {
            return curl_post('https://pay.ir/pg/send', [
                'api' => $api,
                'amount' => $amount,
                'redirect' => $redirect,
            ]);
        }

        function verify($api, $token)
        {
            return curl_post('https://pay.ir/pg/verify', [
                'api' => $api,
                'token' => $token,
            ]);
        }

        function curl_post($url, $params)
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            $res = curl_exec($ch);
            curl_close($ch);

            return $res;
        }

        $id = $order->getId();
        $result = send('test', intval($order->getAmount()) * 10, "https://localhost:8000/api/verifyorder?o=$id");
        $result = json_decode($result);

        if ($result->status) {
            $go = "https://pay.ir/pg/$result->token";
            $response['hasError'] = false;
            $response['redirect'] = $go;
        } else {
            $response['errorMessage'] = $result->errorMessage;
        }
        return new JsonResponse(json_encode($response));
    }

    /**
     * @Route("/cart/cancelorder", name="landingCancelOrder", methods={"POST"})
     */
    public function landingCancelOrder(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        $response['hasError'] = true;

        try {
            $order = $entityManager->createQueryBuilder()
                ->select('o')
                ->from('App:Order', 'o')
                ->where('o.owner = :owner')
                ->andWhere('o.status = :status')
                ->setParameters([
                    'owner' => $this->getUser(),
                    'status' => "pending-payment"
                ])->getQuery()->getSingleResult();
        } catch (NoResultException | NonUniqueResultException $e) {
            $order = [];
        }

        if (empty($order)) {
            $response['errorMessage'] = 'سفارشی پیدا نشد';
            return new JsonResponse(json_encode($response));
        }

        $order->setStatus('failed');
        $entityManager->persist($order);
        $entityManager->flush();

        $response['hasError'] = false;

        return new JsonResponse(json_encode($response));
    }
}