<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use AppBundle\Entity\User;
use AppBundle\Entity\Category;
use AppBundle\Form\UserType;

class DefaultController extends Controller
{
    private $apiKey = '83ec59e2ae1fb050b49802a0f6a9356a';

    /**
     * @Template
     * @Route("/", name="index_page")
     */
    public function indexAction()
    {
        return ['categories' => $this->getUserCategories()];
    }

    /**
     * @Template
     * @Route("/tag/{tag}/{page}", defaults={"page" = 1}, name="pictures_by_tag", requirements={"page" = "\d+"})
     */
    public function picturesByTagAction($tag, $page)
    {
        $userCategories = $this->getUserCategories();
        $url = $this->getFlickrURL(20, $tag, $page);

        $response = json_decode(file_get_contents($url));
        $photo_array = $response->photos->photo;

        $list = [];
        $size = 'm';

        foreach($photo_array as $single_photo){
            $list[] = [
                'farm_id' => $single_photo->farm,
                'server_id' => $single_photo->server,
                'photo_id' => $single_photo->id,
                'secret_id' => $single_photo->secret,
                'title' => $single_photo->title,
            ];

            $picIndex = count($list) - 1;

            $list[$picIndex]['photo_url'] = 'https://farm'.$list[$picIndex]['farm_id'].'.staticflickr.com/'.$list[$picIndex]['server_id'].'/'.
                $list[$picIndex]['photo_id'].'_'.$list[$picIndex]['secret_id'].'_'.$size.'.'.'jpg';

            $list[$picIndex]['img'] = "<img class='thumbnail img-responsive' title='".$list[$picIndex]['title']."' src='".$list[$picIndex]['photo_url']."' />";

        }

        return ['categories' => $userCategories, 'pictures' => $list];
    }

    /**
     * @Template
     * @Route("/show/{tag}/{picture_id}", name="show_picture")
     */
    public function showPictureAction($tag, $picture_id)
    {
        $query = "https://api.flickr.com/services/rest/?method=flickr.photos.getInfo&api_key=".$this->apiKey."&photo_id=".$picture_id.
            "&format=json&nojsoncallback=1";

        $picData = json_decode(file_get_contents($query));

        $picUrl = 'https://farm'.$picData->photo->farm.'.staticflickr.com/'.$picData->photo->server.'/'.
            $picData->photo->id.'_'.$picData->photo->secret.'.jpg';

        return ['pic_data' => $picData, 'pic_url' => $picUrl, 'categories' => $this->getUserCategories()];
    }

    /**
     * @Template
     * @Route("/register", name="register")
     */
    public function registerAction(Request $request)
    {
        $user = new User();

        $form = $this->createForm(UserType::class, $user, ['validation_groups' => ['registration']]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('index_page');
        }

        return ['form' => $form->createView()];
    }

    /**
     * @Template
     * @Route("/login", name="login")
     */
    public function loginAction(Request $request)
    {
        $authenticationUtils = $this->get('security.authentication_utils');

        $error = $authenticationUtils->getLastAuthenticationError();

        return ['error' => $error];
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logoutAction()
    {
    }

    /**
     * @Template
     * @Route("/dashboard", name="dashboard")
     */
    public function dashboardAction()
    {
        $userCategoriesList = $this->getUserCategories();

        return ['categories' => $userCategoriesList];
    }

    private function getUserCategories()
    {
        $user = $this->getUser();
        if($user != null) {
            $userCategoriesList = $this->getDoctrine()->getRepository('AppBundle\Entity\Category')->findByUser($user->getId());
            return $userCategoriesList;
        }

        return [];
    }

    /**
     * @Route("/delcat", name="delcat")
     * @Method({"POST"})
     */
    public function delCategoryAction(Request $request)
    {
        $response = new JsonResponse();

        if ($request->request->has('cat_id')) {
            $currentCategory = $this->getDoctrine()->getRepository('AppBundle\Entity\Category')->find($request->request->get('cat_id'));

            $em = $this->getDoctrine()->getEntityManager();

            try {
                $em->remove($currentCategory);
                $em->flush();

                $response->setData(['success' => true]);
            } catch (\Exception $e) {
                $response->setData(['success' => false]);
            }
        } else $response->setData(['success' => false]);

        return $response;
    }

    /**
     * @Route("/updcat", name="updcat")
     * @Method({"POST"})
     */
    public function updCategoryAction(Request $request)
    {
        $response = new JsonResponse();

        if ($request->request->has('cat_id') && $request->request->has('cat_name')) {
            //check for TAG
            $imagesLengthByTheCatName = $this->checkCategoryNameAsFlickTag($request->request->get('cat_name'));

            //there is this TAG
            if ($imagesLengthByTheCatName > 0) {
                $currentCategory = $this->getDoctrine()->getRepository('AppBundle\Entity\Category')->find($request->request->get('cat_id'));
                $currentCategory->setName($request->request->get('cat_name'));

                $em = $this->getDoctrine()->getEntityManager();

                try {
                    $em->persist($currentCategory);
                    $em->flush();

                    $response->setData(['success' => true]);
                } catch (\Exception $e) {
                    $response->setData(['success' => false]);
                }
            }
            else $response->setData(['success' => false]);
        }
        else $response->setData(['success' => false]);

        return $response;
    }

    private function getFlickrURL($perPage, $catName, $page = 1)
    {
        $url = 'https://api.flickr.com/services/rest/?method=flickr.photos.search';
        $url .= '&api_key=' . $this->apiKey;
        $url .= '&tags=' . $catName;
        $url .= '&page=' . $page;
        $url .= '&per_page=' . $perPage;
        $url .= '&format=json';
        $url .= '&nojsoncallback=1';

        return $url;
    }

    private function checkCategoryNameAsFlickTag($catName)
    {
        //check if there is this category in flickr
        $url = $this->getFlickrURL(1, $catName);

        $response = json_decode(file_get_contents($url));
        $photo_array = $response->photos->photo;

        return count($photo_array);
    }

    /**
     * @Route("/insertcat", name="insertcat")
     * @Method({"POST"})
     */
    public function insCategoryAction(Request $request)
    {
        $response = new JsonResponse();

        if ($request->request->has('cat_name')) {
            $userId = $this->get('security.token_storage')->getToken()->getUser()->getId();
            $issetCategory = false;

            $categories = $this->getDoctrine()->getRepository('AppBundle\Entity\Category')->findByName($request->request->get('cat_name'));

            if (count($categories) > 0) {
                foreach ($categories as $cat) {
                    if ($cat->getUser()->getId() == $userId) {
                        $issetCategory = true;
                        break;
                    }
                }
            }

            if ($issetCategory === false) {
                //check for TAG
                $imagesLengthByTheCatName = $this->checkCategoryNameAsFlickTag($request->request->get('cat_name'));

                //there is this TAG
                if ($imagesLengthByTheCatName > 0) {
                    //create new category
                    $user = $this->getDoctrine()->getRepository('AppBundle\Entity\User')->find($userId);

                    $category = new Category();
                    $category->setName($request->request->get('cat_name'));
                    $category->setUser($user);

                    $em = $this->getDoctrine()->getEntityManager();

                    try {
                        $em->persist($category);
                        $em->flush();

                        $response->setData(['success' => $category->getId()]);
                    } catch (\Exception $e) {
                        $response->setData(['success' => false]);
                    }
                }
                else $response->setData(['success' => false]);
            }
            else $response->setData(['success' => false]);

        }
        else $response->setData(['success' => false]);

        return $response;
    }
}
