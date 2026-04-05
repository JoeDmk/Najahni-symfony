<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\Post;
use App\Entity\PostReaction;
use App\Entity\Thread;
use App\Repository\EventRepository;
use App\Repository\GroupRepository;
use App\Repository\PostReactionRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/community')]
#[IsGranted('ROLE_USER')]
class CommunityController extends AbstractController
{
    // ========== POSTS ==========
    #[Route('/posts', name: 'app_community_posts')]
    public function posts(PostRepository $repo): Response
    {
        $posts = $repo->findFeed();
        return $this->render('front/community/posts.html.twig', ['posts' => $posts]);
    }

    #[Route('/posts/new', name: 'app_community_post_new', methods: ['POST'])]
    public function newPost(Request $request, EntityManagerInterface $em): Response
    {
        $post = new Post();
        $post->setUser($this->getUser());
        $post->setContent($request->request->get('content'));
        $post->setCreatedAt(new \DateTime());

        $em->persist($post);
        $em->flush();
        $this->addFlash('success', 'Publication créée !');
        return $this->redirectToRoute('app_community_posts');
    }

    #[Route('/posts/{id}/react', name: 'app_community_post_react', methods: ['POST'])]
    public function reactPost(Post $post, Request $request, EntityManagerInterface $em, PostReactionRepository $reactionRepo): Response
    {
        $type = $request->request->get('type', 'LIKE');
        $existing = $reactionRepo->findByPostAndUser($post, $this->getUser());

        if ($existing) {
            if ($existing->getReactionType() === $type) {
                $em->remove($existing);
            } else {
                $existing->setReactionType($type);
            }
        } else {
            $reaction = new PostReaction();
            $reaction->setPost($post);
            $reaction->setUser($this->getUser());
            $reaction->setReactionType($type);
            $em->persist($reaction);
        }

        $em->flush();
        return $this->redirectToRoute('app_community_posts');
    }

    #[Route('/posts/{id}/delete', name: 'app_community_post_delete', methods: ['POST'])]
    public function deletePost(Post $post, EntityManagerInterface $em): Response
    {
        if ($post->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($post);
        $em->flush();
        $this->addFlash('success', 'Publication supprimée.');
        return $this->redirectToRoute('app_community_posts');
    }

    // ========== GROUPS ==========
    #[Route('/groups', name: 'app_community_groups')]
    public function groups(GroupRepository $repo): Response
    {
        $groups = $repo->findAll();
        return $this->render('front/community/groups.html.twig', ['groups' => $groups]);
    }

    #[Route('/groups/new', name: 'app_community_group_new', methods: ['GET', 'POST'])]
    public function newGroup(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $group = new Group();
            $group->setName($request->request->get('name'));
            $group->setDescription($request->request->get('description'));
            $group->setGroupAdmin($this->getUser());
            $group->setCreatedAt(new \DateTime());
            $group->setIsPrivate($request->request->getBoolean('is_private'));

            $em->persist($group);
            $em->flush();

            // Add creator as member
            $member = new GroupMember();
            $member->setGroup($group);
            $member->setUser($this->getUser());
            $member->setJoinedAt(new \DateTime());
            $em->persist($member);
            $em->flush();

            $this->addFlash('success', 'Groupe créé !');
            return $this->redirectToRoute('app_community_group_show', ['id' => $group->getId()]);
        }

        return $this->render('front/community/group_form.html.twig');
    }

    #[Route('/groups/{id}', name: 'app_community_group_show', requirements: ['id' => '\d+'])]
    public function showGroup(Group $group, ThreadRepository $threadRepo): Response
    {
        $threads = $threadRepo->findByGroup($group);
        return $this->render('front/community/group_show.html.twig', [
            'group' => $group,
            'threads' => $threads,
        ]);
    }

    // ========== THREADS ==========
    #[Route('/groups/{id}/threads/new', name: 'app_community_thread_new', methods: ['POST'])]
    public function newThread(Group $group, Request $request, EntityManagerInterface $em): Response
    {
        $thread = new Thread();
        $thread->setGroup($group);
        $thread->setUser($this->getUser());
        $thread->setTitle($request->request->get('title'));
        $thread->setContent($request->request->get('content'));
        $thread->setCreatedAt(new \DateTime());

        $em->persist($thread);
        $em->flush();
        $this->addFlash('success', 'Discussion créée !');
        return $this->redirectToRoute('app_community_thread_show', ['id' => $thread->getId()]);
    }

    #[Route('/threads/{id}', name: 'app_community_thread_show')]
    public function showThread(Thread $thread): Response
    {
        return $this->render('front/community/thread_show.html.twig', ['thread' => $thread]);
    }

    #[Route('/threads/{id}/comment', name: 'app_community_thread_comment', methods: ['POST'])]
    public function commentThread(Thread $thread, Request $request, EntityManagerInterface $em): Response
    {
        $comment = new Comment();
        $comment->setThread($thread);
        $comment->setUser($this->getUser());
        $comment->setContent($request->request->get('content'));
        $comment->setCreatedAt(new \DateTime());

        $em->persist($comment);
        $em->flush();
        return $this->redirectToRoute('app_community_thread_show', ['id' => $thread->getId()]);
    }

    // ========== EVENTS ==========
    #[Route('/events', name: 'app_community_events')]
    public function events(EventRepository $repo): Response
    {
        $events = $repo->findUpcoming(50);
        return $this->render('front/community/events.html.twig', ['events' => $events]);
    }

    #[Route('/events/new', name: 'app_community_event_new', methods: ['GET', 'POST'])]
    public function newEvent(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $event = new Event();
            $event->setTitle($request->request->get('title'));
            $event->setDescription($request->request->get('description'));
            $event->setEventDate(new \DateTime($request->request->get('event_date')));
            $event->setCapacity((int) $request->request->get('capacity'));
            $event->setCreatedBy($this->getUser());
            $event->setCreatedAt(new \DateTime());

            $em->persist($event);
            $em->flush();
            $this->addFlash('success', 'Événement créé !');
            return $this->redirectToRoute('app_community_events');
        }

        return $this->render('front/community/event_form.html.twig');
    }

    #[Route('/events/{id}/join', name: 'app_community_event_join', methods: ['POST'])]
    public function joinEvent(Event $event, EntityManagerInterface $em): Response
    {
        if (!$event->hasCapacity()) {
            $this->addFlash('danger', 'L\'événement est complet.');
            return $this->redirectToRoute('app_community_events');
        }

        $participant = new EventParticipant();
        $participant->setEvent($event);
        $participant->setUser($this->getUser());
        $em->persist($participant);
        $em->flush();

        $this->addFlash('success', 'Inscription confirmée !');
        return $this->redirectToRoute('app_community_events');
    }
}
