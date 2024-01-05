<?php

namespace App\Controller;

use DateTime;
use App\Entity\User;
use App\Form\UserType;
use App\Service\Uploader;
use App\Entity\ResetPassword;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ResetPasswordRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class SecurityController extends AbstractController
{

    public function __construct(
        private FormLoginAuthenticator $authenticator
    ) {
    }


    #[Route('/signup', name: 'signup')]
    public function signup(UpLoader $uploader, UserAuthenticatorInterface $userAuthenticator, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer)    {

        $user = new User();
        $userForm = $this->createForm(UserType::class, $user);
        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            $picture = $userForm->get('pictureFile')->getData();    //Je recupere l'image
            $user->setPicture($uploader->uploadProfileImage($picture));   // je recupére le dossier du public path et j'y ajoute le filename

            $hash = $passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hash);
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Bienvenue sur mon site.');

            $email = new TemplatedEmail();
            $email  ->to($user->getEmail())
                    ->subject('Bienvenu sur wonder')
                    ->htmlTemplate('@email_templates/welcome.html.twig')
                    ->context([
                        'username' => $user->getFirstname()
                    ]);

            $mailer->send($email);

            // return $this->redirectToRoute('login');
            return $userAuthenticator->authenticateUser($user, $this->authenticator, $request);
            // return $userAuthenticator->authenticateUser($user, $appAuthenticator, $request);

            //https://roadtodev.formator.io/articles/symfony-6-authentifier-lutilisateur-apres-linscription 
        }

        return $this->render('security/signup.html.twig', ['form' => $userForm->createView()]);

        return $this->render('security/signup.html.twig', [
            'form'=>$userForm->createView()
        ]);
    }


    #[Route('/login', name: 'login')]
    
    public function login(AuthenticationUtils $authenticationUtils): Response
    {

        if ($this->getUser()){
            $this->redirectToRoute('home');
        }


        $error = $authenticationUtils->getLastAuthenticationError();
        $username = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'error' => $error,
            'username' => $username
        ]);
    }




    #[Route('/logout', name: 'logout')]
    public function logout()
    {
        
    }




    #[Route('/reset_password/{token}', name: 'reset_password')]
    public function resetPassword(RateLimiterFactory $passwordRecovryLimiter, Request $request, UserRepository $userRepository, EntityManagerInterface $em, string $token, ResetPasswordRepository $resetPasswordRepository, UserPasswordHasherInterface $userPasswordHasher)
    {

        $limiter = $passwordRecovryLimiter->create($request->getClientIp());
        if (false===$limiter->consume(1)->isAccepted()){
            $this->addFlash('error', 'vous devez attendre 60 minute pour refaire une tentative');
            return $this->redirectToRoute('login');
        }


        $resetPassword = $resetPasswordRepository->findOneBy(['token' => sha1($token)]);
        if(!$resetPassword || $resetPassword->getExpiredAt() < new DateTime('now')){
            if($resetPassword){
                $em->remove($resetPassword);
                $em-> flush();
            }
            $this->addFlash('error', 'Votre demande à expirée, veuillez renouveler votre demande');
            return $this->redirectToRoute('login');
        }


        $passwordForm = $this->createFormBuilder()
        ->add('password', PasswordType::class,[
            'label' => 'Nouveau mot de passe',
            'constraints' => [
                new NotBlank([
                    'message' => 'Veuillez renseigner votre mot de passe'
                ]),
                new Length([
                    'min' => 6,
                    'minMessage' => 'Le mot  de passe doit faire au moins 6 caratères.'
                ])
            ]
        ])
        ->getForm();

        $passwordForm->handleRequest($request);
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()){
            $password = $passwordForm->get('password')->getData();
            $user = $resetPassword->getUser();
            $hash = $userPasswordHasher->hashPassword($user, $password);
            $user->setPassword($hash);

            $em->flush();

            $this->addFlash('success', 'Votre mot de passe à bien été changé.');
            return $this->redirectToRoute('login');
        }

        return $this->render('security/reset_password_form.html.twig', [
            'form' => $passwordForm->createView()
        ]);
    }



    #[ROUTE('/reset_password_request', name: 'reset_password_request')]

    public function resetPasswordRequest(RateLimiterFactory $passwordRecovryLimiter,  MailerInterface $mailer,  Request $request, UserRepository $userRepository, ResetPasswordRepository $resetPasswordRepository, EntityManagerInterface $em ){

        $limiter = $passwordRecovryLimiter->create($request->getClientIp());
        if (false===$limiter->consume(1)->isAccepted()){
            $this->addFlash('error', 'vous devez attendre 60 minute pour refaire une tentative');
            return $this->redirectToRoute('login');
        }

        $emailForm = $this->createFormBuilder()->add('email', EmailType::class, [
            'constraints' => [
                new NotBlank([
                    'message' => 'Veuillez renseigner votre email'
                ])
            ]
        ])->getForm();

        $emailForm->handleRequest($request);
            if($emailForm->isSubmitted() && $emailForm->isValid()){
                $emailValue = $emailForm->get('email')->getData();
                $user = $userRepository->findOneBy(['email' => $emailValue]);

                if($user) {

                    $oldResetPassword = $resetPasswordRepository->findOneBy(['user' => $user]);
                    if($oldResetPassword){
                        $em->remove($oldResetPassword);
                        $em->flush();
                    }

                    $resetPassword = new ResetPassword();
                    $resetPassword->setUser($user);
                    $resetPassword->setExpiredAt(new \DateTimeImmutable('+2 hours'));
                    $token = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(30))), 0, 20);
                    $resetPassword->setToken(sha1($token));
                    $em->persist($resetPassword);
                    $em->flush();
                    $email = new TemplatedEmail();
                    $email->to($emailValue)
                            ->subject('Demande de réinitialisation de mot de passe')
                            ->htmlTemplate('@email_templates/reset_password_request.html.twig')
                            ->context([
                                'token' =>  $token
                            ]);
                    $mailer->send($email);
                    return $this->redirectToRoute('home');
                    

                }
                $this->addFlash('success', 'Un Email vous a été envoyé pour réinitialiser votre mot de passe');
            }


        return $this->render('security/reset_password_request.html.twig',[
            'form' => $emailForm->createView()
        ]);
    }
}
