<?php

namespace App\Controller\Admin;

use App\Entity\Group;
use App\Entity\Post;
use App\Entity\Event;
use App\Repository\GroupRepository;
use App\Repository\PostRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/community')]
#[IsGranted('ROLE_ADMIN')]
class AdminCommunityController extends AbstractController
{
    #[Route('/groups', name: 'admin_community_groups')]
    public function groups(Request $request, GroupRepository $repo, PaginatorInterface $paginator): Response
    {
        $qb = $repo->createQueryBuilder('g')->orderBy('g.createdAt', 'DESC');
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 15);
        return $this->render('admin/community/groups.html.twig', ['pagination' => $pagination]);
    }

    #[Route('/groups/{id}/delete', name: 'admin_community_groups_delete', methods: ['POST'])]
    public function deleteGroup(Group $group, EntityManagerInterface $em): Response
    {
        $em->remove($group);
        $em->flush();
        $this->addFlash('success', 'Groupe supprimé.');
        return $this->redirectToRoute('admin_community_groups');
    }

    #[Route('/posts', name: 'admin_community_posts')]
    public function posts(Request $request, PostRepository $repo, PaginatorInterface $paginator): Response
    {
        $qb = $repo->createQueryBuilder('p')->orderBy('p.createdAt', 'DESC');
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 15);
        return $this->render('admin/community/posts.html.twig', ['pagination' => $pagination]);
    }

    #[Route('/posts/{id}/delete', name: 'admin_community_posts_delete', methods: ['POST'])]
    public function deletePost(Post $post, EntityManagerInterface $em): Response
    {
        $em->remove($post);
        $em->flush();
        $this->addFlash('success', 'Publication supprimée.');
        return $this->redirectToRoute('admin_community_posts');
    }

    #[Route('/events', name: 'admin_community_events')]
    public function events(Request $request, EventRepository $repo, PaginatorInterface $paginator): Response
    {
        $qb = $repo->createQueryBuilder('e')->orderBy('e.eventDate', 'DESC');
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 15);
        return $this->render('admin/community/events.html.twig', ['pagination' => $pagination]);
    }

    #[Route('/events/{id}/delete', name: 'admin_community_events_delete', methods: ['POST'])]
    public function deleteEvent(Event $event, EntityManagerInterface $em): Response
    {
        $em->remove($event);
        $em->flush();
        $this->addFlash('success', 'Événement supprimé.');
        return $this->redirectToRoute('admin_community_events');
    }
}
