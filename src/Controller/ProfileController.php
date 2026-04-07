<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\LoginHistoryRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(
        LoginHistoryRepository $loginHistoryRepo,
        NotificationRepository $notifRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render('front/profile/index.html.twig', [
            'loginHistory' => $loginHistoryRepo->findByUser($user->getId(), 10),
            'unreadNotifs' => $notifRepo->countUnread($user->getId()),
        ]);
    }

    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $oldEmail = $user->getEmail();
            $oldPhone = $user->getPhone();

            $user->setFirstname(trim($request->request->get('firstname', '')));
            $user->setLastname(trim($request->request->get('lastname', '')));
            $newEmail = trim($request->request->get('email', ''));
            if ($newEmail !== $oldEmail) {
                $user->setEmail($newEmail);
                $user->setVerified(false);
            }
            $newPhone = $request->request->get('phone');
            if ($newPhone !== $oldPhone) {
                $user->setPhone($newPhone);
                $user->setPhoneVerified(false);
            }
            $user->setBio($request->request->get('bio'));
            $user->setCompanyName($request->request->get('company_name'));
            $user->setLinkedinUrl($request->request->get('linkedin_url'));
            $user->setAddress($request->request->get('address'));
            $user->setPreferredLanguage($request->request->get('preferred_language', 'fr'));
            $user->setPreferredTheme($request->request->get('preferred_theme', 'light'));

            $dob = $request->request->get('date_of_birth');
            if ($dob) {
                $user->setDateOfBirth(new \DateTime($dob));
            }

            $file = $request->files->get('profile_picture');
            if ($file) {
                $safeName = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeName . '-' . uniqid() . '.' . $file->guessExtension();
                try {
                    $file->move($this->getParameter('kernel.project_dir') . '/public/uploads/profiles', $newFilename);
                    $user->setProfilePicture('/uploads/profiles/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors du téléchargement de l\'image.');
                }
            }

            $em->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('front/profile/edit.html.twig');
    }

    #[Route('/profile/password', name: 'app_profile_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        EmailService $emailService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $current = $request->request->get('current_password');
        $new = $request->request->get('new_password');
        $confirm = $request->request->get('confirm_password');

        if (!$hasher->isPasswordValid($user, $current)) {
            $this->addFlash('danger', 'Mot de passe actuel incorrect.');
            return $this->redirectToRoute('app_profile_edit');
        }
        if ($new !== $confirm) {
            $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_profile_edit');
        }
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $new)) {
            $this->addFlash('danger', 'Le mot de passe doit contenir au moins 6 caractères, une majuscule, une minuscule et un chiffre.');
            return $this->redirectToRoute('app_profile_edit');
        }

        $user->setPassword($hasher->hashPassword($user, $new));
        $em->flush();

        try {
            $emailService->sendPasswordChangeConfirmation($user->getEmail(), $user->getFirstname());
        } catch (\Exception $e) {}

        $this->addFlash('success', 'Mot de passe modifié avec succès !');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/verify-email', name: 'app_profile_verify_email', methods: ['POST'])]
    public function verifyEmailFromProfile(
        Request $request,
        EntityManagerInterface $em,
        EmailService $emailService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $isAjax = $request->isXmlHttpRequest();

        $action = $request->request->get('action');
        if ($action === 'send') {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $user->setVerificationCode($code);
            $user->setVerificationCodeExpiresAt(new \DateTime('+5 minutes'));
            $em->flush();

            try {
                $emailService->sendVerificationCode($user->getEmail(), $code, $user->getFirstname());
                if ($isAjax) {
                    return new JsonResponse(['success' => true, 'message' => 'Code envoyé à ' . $user->getEmail()]);
                }
                $this->addFlash('success', 'Code de vérification envoyé à ' . $user->getEmail());
            } catch (\Exception $e) {
                if ($isAjax) {
                    return new JsonResponse(['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email.'], 500);
                }
                $this->addFlash('danger', 'Erreur lors de l\'envoi de l\'email.');
            }
        } elseif ($action === 'verify') {
            $code = $request->request->get('code', '');
            if ($code === $user->getVerificationCode()
                && $user->getVerificationCodeExpiresAt() > new \DateTime()) {
                $user->setVerified(true);
                $user->setVerificationCode(null);
                $user->setVerificationCodeExpiresAt(null);
                $em->flush();
                if ($isAjax) {
                    return new JsonResponse(['success' => true, 'message' => 'Email vérifié avec succès !']);
                }
                $this->addFlash('success', 'Email vérifié avec succès !');
            } else {
                if ($isAjax) {
                    return new JsonResponse(['success' => false, 'message' => 'Code incorrect ou expiré.'], 400);
                }
                $this->addFlash('danger', 'Code incorrect ou expiré.');
            }
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/notifications', name: 'app_profile_notifications')]
    public function notifications(NotificationRepository $notifRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render('front/profile/notifications.html.twig', [
            'notifications' => $notifRepo->findByUser($user->getId(), 50),
        ]);
    }

    #[Route('/profile/notifications/read-all', name: 'app_profile_notifications_read_all', methods: ['POST'])]
    public function markAllNotificationsRead(NotificationRepository $notifRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $notifRepo->markAllAsRead($user->getId());
        $this->addFlash('success', 'Toutes les notifications marquées comme lues.');
        return $this->redirectToRoute('app_profile_notifications');
    }

    #[Route('/profile/login-history', name: 'app_profile_login_history')]
    public function loginHistory(LoginHistoryRepository $loginHistoryRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render('front/profile/login_history.html.twig', [
            'history' => $loginHistoryRepo->findByUser($user->getId(), 50),
        ]);
    }
}
