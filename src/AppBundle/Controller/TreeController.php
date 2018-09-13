<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class TreeController extends Controller
{
    /**
     * @Route("/tree/index", name="tree_index")
     */
    public function indexAction()
    {
        $first_user = $this->em()->getRepository('AppBundle:User')->findFirstUser();
        return $this->render('AppBundle:Tree:index.html.twig', array(
            'first_user' => $first_user,
        ));
    }

    /**
     * @Route("/tree/show/{id}", name="tree_show")
     */
    public function showAction($id)
    {
        return $this->render('AppBundle:Tree:show.html.twig', array(
            // ...
        ));
    }

}
