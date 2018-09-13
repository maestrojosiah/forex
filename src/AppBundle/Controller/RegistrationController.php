<?php 

namespace AppBundle\Controller;

use AppBundle\Form\UserType;
use AppBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class RegistrationController extends Controller
{
    /**
     * @Route("/register", name="user_registration")
     */
    public function registerAction(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        // 1) build the form
        $user = new User();
        $users_count = $this->em()->getRepository('AppBundle:User')->countUsers();
        $users = $this->em()->getRepository('AppBundle:User')->findAll();
        $find_with_least_children = $this->hasLeastChildren($users);
        $form = $this->createForm(UserType::class, $user);

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if($users_count < 1){
                $user->setReferrer(null);
            } else {
                $referrers_code = $form->get('referrer')->getData();
                $referrers_id = $this->getIdFromCode($referrers_code);
                $referrer = $this->find('User', $referrers_id);
                $all_children = $this->getAllChildren($referrer);
                if(count($all_children) < 3){
                    //less than three children
                    $user->setReferrer($referrers_id);
                } else {
                    //change to L0.
                    $referrer->setLevel("L0");
                    $this->save($referrer);
                    //enough children use one of children as referrer instead of this
                    $children = $this->getAllChildren($referrer);
                    $to_use_as_referrer = $this->hasLeastChildren($children);
                    if($to_use_as_referrer !== null){
                        $user->setReferrer($to_use_as_referrer->getId());
                    } else {
                        //redirect to another page
                        $this->hasEnoughChildren($children);
                        dump($to_use_as_referrer);
                        return $this->redirectToRoute('user_registration');
                    }
                }
                
            }

            $user->setLevel("L1");

            // 3) Encode the password (you could also do this via Doctrine listener)
            $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
            $user->setPassword($password);

            // 4) save the User!
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $refer_code = $this->generateCode($user->getId());
            $user->setCode($refer_code);
            $this->save($user);

            // ... do any other work - like sending them an email, etc
            // maybe set a "flash" success message for the user

            return $this->redirectToRoute('login', ['ref' => $refer_code]);
        }

        return $this->render(
            'registration/register.html.twig',
            array('form' => $form->createView(), 'users_count' => $users_count, 'test' => $find_with_least_children)
        );
    }

    private function activateUser($user){
        $user->setActive(true);
        $this->save($user);
    }

    private function getNextId(){
        $next_id = 0;
        $last_user = $this->em()->getRepository("AppBundle:User")->findLastUser();
        
        if($last_user == null){
            $next_id = 1;
        } else {
            $last_user_id = $last_user->getId();
            $next_id = $last_user_id + 1;
        }
        return $next_id;
    }

    private function generateCode($id){

        $a = $b = $c = $d = $e = $f = '';

        $a .= chr(mt_rand(65, 90));    
        $b .= mt_rand(0, 9);
        $c .= chr(mt_rand(65, 90));    
        $d .= mt_rand(0, 9);
        $e .= chr(mt_rand(65, 90));    
        $f .= mt_rand(0, 9);
        

        return $a.$b.$c.$id.$d.$e.$f;        
    }

    private function getIdFromCode($code){
        $id = substr($code, 3, -3);
        return $id;
    }


    private function countChildren($user){
        return $this->em()->getRepository('AppBundle:User')->countAllChildren($user->getId());
    }

    private function hasLeastChildren($users){
        $children_list = [];
        foreach ($users as $user) {
            $children_list[$user->getId()] = $this->countChildren($user);
        }
        $id = array_keys($children_list, min($children_list))[0];  
        $user = $this->find("User", $id);
        $children = $this->getAllChildren($user);
        $user_to_benefit = count($children) < 3 ? $user : null;
        return $user_to_benefit; 
    }

    private function hasEnoughChildren($users){
        $children_list = [];
        foreach ($users as $user) {
            $children_list[$user->getId()] = $this->countChildren($user);
        }
        $ids = array_keys($children_list, max($children_list)); 
        foreach ($ids as $id) {
            $user = $this->find("User", $id);
            $children = $this->getAllChildren($user);
            if(count($children) == 3){
                $user->setLevel("L0");
                $this->save($user);
            }
        }

        return $children_list; 
    }

    private function getAllChildren($user){
        $id = $user->getId();
        $all_children = $this->em()->getRepository('AppBundle:User')
            ->findBy(
                array('referrer' => $id),
                array('id' => 'ASC')
            );
        return $all_children;
    }

    private function em(){
        $em = $this->getDoctrine()->getManager();
        return $em;
    }

    private function find($entity, $id){
        $entity = $this->em()->getRepository("AppBundle:$entity")->find($id);
        return $entity;
    }

    private function save($entity){
        $this->em()->persist($entity);
        $this->em()->flush();
        return true;
    }

    private function delete($entity){
        $this->em()->remove($entity);
        $this->em()->flush();
        return true;
    }

    private function user(){
        $user = $this->container->get('security.token_storage')->getToken()->getUser();
        return $user;
    }


}