<?php

namespace App\Controller\Admin\Course;

use App\Entity\Category;
use App\Entity\Course;
use App\Entity\Session;
use App\Utils\ImageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class AdminCourseController extends AbstractController
{
    /**
     * @Route("/admin/course/list", name="adminCourseGetCourses", methods={"POST"})
     */
    public function adminCourseGetCourses(Request $request, ImageHandler $imageHandler, EntityManagerInterface $entityManager): Response
    {
        $courses = $entityManager->createQueryBuilder()
            ->select('c')
            ->from('App:Course', 'c')
            ->getQuery()->execute();
        $results = [];
        /**
         * @var  $course Course
         */
        foreach ($courses as $index => $course) {
            $results[$index]['title'] = $course->getTitle();
            $results[$index]['slug'] = $course->getSlug();
            $results[$index]['status'] = $course->getStatus();
        }

        return new JsonResponse(json_encode($results));
    }

    /**
     * @Route("/admin/course/newcourse", name="adminCourseNew", methods={"POST"})
     */
    public function adminCourseNew(Request $request, ImageHandler $imageHandler, EntityManagerInterface $entityManager): Response
    {
        $response['hasError'] = true;

        $credentials = $request->request->all();

        $categories = json_decode($credentials['categories']);

        foreach ($credentials as $credential) {
            if (empty($credential)) {
                $response['errorMessage'] = 'اطلاعات ناقص وارد شده است';
                return new JsonResponse(json_encode($response));
            }
        }

        $categories_object = [];
        foreach ($categories as $category) {
            $categories_object[] = $entityManager->getRepository(Category::class)->findOneBy(['en_name' => $category]);
        }

        $course = (new Course())
            ->setStatus('init')
            ->setLevel($credentials['level'])
            ->setCourseDesc($credentials['desc'])
            ->setAuthorDesc($credentials['author-desc'])
            ->setPrice($credentials['price'])
            ->setTitle($credentials['title'])
            ->setSlug($credentials['slug'])
            ->setImage($credentials['course-image'])
            ->setTotalDuration(0)
            ->setSessionsCount(0);

        foreach ($categories_object as $item) {
            $course->addCategory($item);
        }

        $entityManager->persist($course);
        $entityManager->flush();

        $response['hasError'] = false;
        return new JsonResponse(json_encode($response));
    }

    /**
     * @Route("/admin/course/editcourse", name="adminCourseEditCourse", methods={"POST"})
     */
    public function adminCourseEditCourse(Request $request, ImageHandler $imageHandler, EntityManagerInterface $entityManager): Response
    {
        $response['hasError'] = true;

        $credentials = $request->request->all();

        $categories = json_decode($credentials['categories']);

        foreach ($credentials as $credential) {
            if (empty($credential)) {
                $response['errorMessage'] = 'اطلاعات ناقص وارد شده است';
                return new JsonResponse(json_encode($response));
            }
        }

        $course = $entityManager->getRepository(Course::class)->find($credentials['course_id']);

        $categories_object = [];
        foreach ($categories as $category) {
            $categories_object[] = $entityManager->getRepository(Category::class)->findOneBy(['en_name' => $category]);
        }

        $course->setStatus('init')
            ->setLevel($credentials['level'])
            ->setCourseDesc($credentials['desc'])
            ->setAuthorDesc($credentials['author-desc'])
            ->setPrice($credentials['price'])
            ->setTitle($credentials['title'])
            ->setSlug($credentials['slug'])
            ->setImage($credentials['course-image'])
            ->setStatus($credentials['status']);

        $current_categories = $course->getCategories();
        foreach ($current_categories as $current_category) {
            $course->removeCategory($current_category);
        }

        foreach ($categories_object as $item) {
            $course->addCategory($item);
        }

        $entityManager->persist($course);
        $entityManager->flush();

        $response['hasError'] = false;
        return new JsonResponse(json_encode($response));
    }

    /**
     * @Route("/admin/course/getSingleCourse/{slug}", name="adminCourseGetSingleCourse", methods={"POST"})
     */
    public function adminCourseGetSingleCourse(Request $request, ImageHandler $imageHandler, EntityManagerInterface $entityManager, SerializerInterface $serializer, $slug): Response
    {
        $course = $entityManager->getRepository(Course::class)->findOneBy(['slug' => $slug]);
        $result = $serializer->serialize($course, 'json', ['ignored_attributes' => ['owner', 'courses']]);

        return new JsonResponse($result);
    }

    /**
     * @Route("/admin/course/getsessions/{slug}", name="adminCourseGetSessions", methods={"POST"})
     */
    public function adminCourseGetSessions(Request $request, ImageHandler $imageHandler, EntityManagerInterface $entityManager, SerializerInterface $serializer, $slug): Response
    {
        $course = $entityManager->getRepository(Course::class)->findOneBy(['slug' => $slug]);
        $sessions = $entityManager->getRepository(Session::class)->findBy(['course' => $course]);
        $result = $serializer->serialize($sessions, 'json', [
            'ignored_attributes' => ['course']
        ]);

        return new JsonResponse($result);
    }
}