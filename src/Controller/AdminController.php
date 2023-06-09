<?php

/********************************************/
/*          PROJET TECHNOLOGIE WEB 2        */
/*     AL NATOUR MAZEN && CAILLAUD TOM      */
/********************************************/

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Form\UserType;
use App\Service\CheckPassword;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    /***************************************************/
    /* Création d'un Admin Dispo que pour Un SuperAdmin
    /***************************************************/
    #[Route('/createadmin', name: 'createadmin')]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function createAdminAction(EntityManagerInterface $em,
                                      UserPasswordHasherInterface $passwordHasher,
                                      CheckPassword $checkPassword,
                                      Request $requete): Response
    {
        // creation de la nouvelle personne
        $NewAdmin = new User();

        //on cree le formulaire et on l'hydrate
        $form =  $this->createForm(UserType::class,$NewAdmin);
        $form->add('send',SubmitType::class,['label' =>'Ajouter un Admin']);
        $form->handleRequest($requete);

        if($form->isSubmitted() && $form->isValid()){
            $NewAdmin->setRoles(['ROLE_ADMIN']);

            if (!$checkPassword->check($NewAdmin->getPassword())) {
                $this->addFlash('info','le mot de passe n est pas valide (service)');
            }
            else {
                //on hashe le mdp de l'utilisateur
                $hashedPassword = $passwordHasher->hashPassword($NewAdmin, $NewAdmin->getPassword());
                $NewAdmin->setPassword($hashedPassword);

                $em->persist($NewAdmin);
                $em->flush();
                $this->addFlash('info','l ajout de l admin a ete effectue !');
                return $this->redirectToRoute('app_accueil');
            }
        }

        $args=array(
            'myform' => $form->createView(),
        );

        return $this->render('Vue/Admin/createAdmin.html.twig', $args);
    }

    /***************************************************/
    /* Action pour afficher les utilisateur du Site
    /***************************************************/
    #[Route('/managecustomers', name: 'managecustomers')]
    #[IsGranted('ROLE_ADMIN')]
    public function manageCustomersAction(EntityManagerInterface $em): Response
    {
        $userRepository = $em->getRepository(User::class);
        $users = $userRepository->findAll();
        return $this->render('Vue/Admin/manageCustomers.html.twig',['clients'=> $users]);
    }

    /***************************************************/
    /* Action pour vider le panier d'un utilisateur par un admin
    /***************************************************/
    #[Route('/clearcartbyadmin/{clientid}',
        name: 'clearcartbyadmin',
        requirements: ['clientid' => '[1-9]\d*']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function clearCartAdminAction(int $clientid,EntityManagerInterface $em): Response
    {
        $userRepository = $em->getRepository(User::class);
        $user = $userRepository->findOneBy(['id' => $clientid]);

        if($user !== null){
                if($this->isSuperAdmin($user)){
                    $this->addFlash('info' , 'Error: vous ne pouvez pas gerer un SuperAdmin !');
                }
                else {
                    // On cherche tout les orders liées au client
                    $orderRepository = $em->getRepository(Order::class);
                    $orders = $orderRepository->findBy(['client' => $user]);
                    if ($orders) {
                        // On vide tout les orders liés à ce client
                        foreach ($orders as $order) {
                            //On re recupere le produit pour le remttre dans la BD
                            $product = $order->getProduit();
                            if ($product) {
                                $product->setQuantite($product->getQuantite() + $order->getQuantite());
                            }
                            //On enleve l'order
                            $em->remove($order);
                        }
                        // On sauvegarde les changment
                        $em->flush();
                        // on ajoute un msg flash pour informé
                        $this->addFlash('info', 'Le Panier a ete vider avec succes !');
                    } else {
                        $this->addFlash('info', 'Cette utilisateur n a pas de panier en cours !');
                    }
                }
        }
        else{
            // on ajoute un msg flash pour informé
            $this->addFlash('info' , 'Cette utilisateurs n existe pas !');
        }
        return $this->redirectToRoute('admin_managecustomers');
    }

    /***************************************************/
    /* Action pour supprimer un utilisateur par un admin
    /***************************************************/
    #[Route('/removeuser/{clientid}',
        name: 'removeuser',
        requirements: ['clientid' => '[1-9]\d*']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function RemoveUserAction(int $clientid,EntityManagerInterface $em): Response
    {
        $userRepository = $em->getRepository(User::class);
        $user = $userRepository->findOneBy(['id' => $clientid]);

        if($user !== null){
            if($this->isSuperAdmin($user)){
                $this->addFlash('info' , 'Error: vous essayez de supprimer un SuperAdmin !');
            }
            else if($this->isSameUser($user)){
                $this->addFlash('info' , 'Error: vous essayez de supprimer vous meme !');
            }
            else {
                $orderRepository = $em->getRepository(Order::class);
                // On cherche tout les orders liées au client
                $orders = $orderRepository->findBy(['client' => $user]);
                if ($orders) {
                    // On vide le panier de l'utilisateur avant de le supprimer
                    $this->clearCartAdminAction($clientid, $em);
                }
                // on le supp de la BD
                $em->remove($user);
                // On sauvegarde les changment
                $em->flush();
                // on ajoute un msg flash pour informé
                $this->addFlash('info', 'L utilisateur a ete supprimer avec succes !');
            }
        }
        else{
            $this->addFlash('info' , 'Cette utilisateurs n existe pas !');
        }
        return $this->redirectToRoute('admin_managecustomers');
    }

    /***************************************************/
    /* Fonction interne pour savoir si l'utilisateur en parametere est un SUPERADMIN
    /***************************************************/
    private function isSuperAdmin(User $user): bool
    {
        return (in_array("ROLE_SUPERADMIN", $user->getRoles()));
    }
    /***************************************************/
    /* Fonction interne pour savoir si l'utilisateur en parametere est lui-même
    /***************************************************/
    private function isSameUser(User $user):bool
    {
        return $user === $this->getUser();
    }
}
