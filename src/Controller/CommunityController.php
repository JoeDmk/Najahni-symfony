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
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use App\Repository\GroupRepository;
use App\Repository\PostReactionRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Service\CommunityAiService;
use App\Service\CommunityPostTranslationService;
use App\Service\CommunityTextModerationService;
use App\Service\CommunityTicketService;
use App\Service\CommunityWeatherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[Route('/community')]
#[IsGranted('ROLE_USER')]
class CommunityController extends AbstractController
{
    private const THREAD_TITLE_MIN_LENGTH = 5;
    private const THREAD_TITLE_MAX_LENGTH = 150;
    private const THREAD_CONTENT_MIN_LENGTH = 15;
    private const THREAD_CONTENT_MAX_LENGTH = 5000;
    private const COMMENT_CONTENT_MIN_LENGTH = 3;
    private const COMMENT_CONTENT_MAX_LENGTH = 2000;

    private const REACTION_META = [
        PostReaction::TYPE_LIKE => ['label' => 'J\'aime', 'emoji' => '👍'],
        PostReaction::TYPE_LOVE => ['label' => 'J\'adore', 'emoji' => '❤️'],
        PostReaction::TYPE_HAHA => ['label' => 'Haha', 'emoji' => '😂'],
        PostReaction::TYPE_WOW => ['label' => 'Waouh', 'emoji' => '😮'],
        PostReaction::TYPE_SAD => ['label' => 'Sad', 'emoji' => '😢'],
        PostReaction::TYPE_ANGRY => ['label' => 'En colere', 'emoji' => '😡'],
    ];

    private const TRANSLATION_LANGUAGES = [
        'fr' => 'Francais',
        'en' => 'English',
        'ar' => 'Arabe',
    ];

    public function __construct(private readonly GroupRepository $groupRepository)
    {
    }

    // ========== POSTS ==========
    #[Route('/posts', name: 'app_community_posts')]
    public function posts(PostRepository $repo, Request $request): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sort = trim((string) $request->query->get('sort', 'newest'));
        $posts = $repo->findFeed();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $posts = array_values(array_filter($posts, static function (Post $post) use ($needle): bool {
                $haystack = mb_strtolower(trim((string) $post->getUser()?->getFullName().' '.(string) $post->getContent()));

                return str_contains($haystack, $needle);
            }));
        }

        usort($posts, static function (Post $left, Post $right) use ($sort): int {
            return match ($sort) {
                'oldest' => ($left->getCreatedAt()?->getTimestamp() ?? 0) <=> ($right->getCreatedAt()?->getTimestamp() ?? 0),
                'reactions' => ($right->getReactionsCount() <=> $left->getReactionsCount())
                    ?: (($right->getCreatedAt()?->getTimestamp() ?? 0) <=> ($left->getCreatedAt()?->getTimestamp() ?? 0)),
                default => ($right->getCreatedAt()?->getTimestamp() ?? 0) <=> ($left->getCreatedAt()?->getTimestamp() ?? 0),
            };
        });

        return $this->render('front/community/posts.html.twig', [
            'posts' => $posts,
            'search' => $search,
            'sort' => $sort,
            'reactionMeta' => self::REACTION_META,
            'translationLanguages' => self::TRANSLATION_LANGUAGES,
        ]);
    }

    #[Route('/posts/new', name: 'app_community_post_new', methods: ['POST'])]
    public function newPost(
        Request $request,
        EntityManagerInterface $em,
        CommunityTextModerationService $textModerationService,
    ): Response
    {
        $content = trim((string) $request->request->get('content', ''));

        try {
            $imageUrl = $this->storePostUpload($request->files->get('image'));
        } catch (Throwable $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('app_community_posts');
        }

        if ($content === '' && $imageUrl === null) {
            $this->addFlash('danger', 'Ajoutez du texte ou une image avant de publier.');

            return $this->redirectToRoute('app_community_posts');
        }

        $moderated = $textModerationService->moderate($content);
        $post = new Post();
        $post->setUser($this->currentUser());
        $post->setContent($moderated['text']);
        $post->setImageUrl($imageUrl);

        $em->persist($post);
        $em->flush();

        if ($moderated['changed']) {
            $this->addFlash('info', 'Le texte a ete legerement modere avant publication.');
        }

        $this->addFlash('success', 'Publication créée !');
        return $this->redirectToRoute('app_community_posts');
    }

    #[Route('/posts/{id}/react', name: 'app_community_post_react', methods: ['POST'])]
    public function reactPost(
        Post $post,
        Request $request,
        EntityManagerInterface $em,
        PostReactionRepository $reactionRepo,
    ): Response
    {
        $user = $this->currentUser();
        $type = strtoupper(trim((string) $request->request->get('reaction', $request->request->get('type', 'LIKE'))));

        if (!isset(self::REACTION_META[$type])) {
            return $this->reactionError($request, 'Reaction invalide.');
        }

        $existing = $reactionRepo->findByPostAndUser($post, $user);

        if ($existing) {
            if ($existing->getReactionType() === $type) {
                $em->remove($existing);
            } else {
                $existing->setReactionType($type);
            }
        } else {
            $reaction = new PostReaction();
            $reaction->setPost($post);
            $reaction->setUser($user);
            $reaction->setReactionType($type);
            $em->persist($reaction);
        }

        $em->flush();
        $em->refresh($post);

        if ($request->isXmlHttpRequest()) {
            return $this->reactionStateResponse($post, $user);
        }

        return $this->redirectToRoute('app_community_posts');
    }

    #[Route('/posts/{id}/react/remove', name: 'app_community_post_unreact', methods: ['POST'])]
    public function unreactPost(
        Post $post,
        Request $request,
        EntityManagerInterface $em,
        PostReactionRepository $reactionRepo,
    ): Response
    {
        $user = $this->currentUser();
        $existing = $reactionRepo->findByPostAndUser($post, $user);

        if ($existing instanceof PostReaction) {
            $em->remove($existing);
            $em->flush();
            $em->refresh($post);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->reactionStateResponse($post, $user);
        }

        return $this->redirectToRoute('app_community_posts');
    }

    #[Route('/posts/{id}/translate', name: 'app_community_post_translate', methods: ['GET'])]
    public function translatePost(
        Post $post,
        Request $request,
        CommunityPostTranslationService $postTranslationService,
    ): JsonResponse
    {
        try {
            $result = $postTranslationService->translate((string) $post->getContent(), (string) $request->query->get('target', 'original'));

            return new JsonResponse([
                'ok' => true,
                'text' => $result['text'],
                'source' => $result['source'],
                'source_label' => $result['source_label'],
                'target' => $result['target'],
                'target_label' => $result['target_label'],
                'is_original' => $result['is_original'],
            ]);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/posts/{id}/update', name: 'app_community_post_update', methods: ['POST'])]
    public function updatePost(
        Post $post,
        Request $request,
        EntityManagerInterface $em,
        CommunityTextModerationService $textModerationService,
    ): Response
    {
        if (!$this->canManagePost($post, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $content = trim((string) $request->request->get('content', ''));
        $oldImageUrl = $post->getImageUrl();
        $imageUrl = $post->getImageUrl();

        if ($request->request->getBoolean('remove_image')) {
            $imageUrl = null;
        }

        try {
            $uploaded = $this->storePostUpload($request->files->get('image'));
        } catch (Throwable $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('app_community_posts');
        }

        if ($uploaded !== null) {
            $imageUrl = $uploaded;
        }

        if ($content === '' && $imageUrl === null) {
            $this->addFlash('danger', 'Une publication doit contenir du texte ou une image.');

            return $this->redirectToRoute('app_community_posts');
        }

        $moderated = $textModerationService->moderate($content);
        $post->setContent($moderated['text']);
        $post->setImageUrl($imageUrl);
        $em->flush();

        if ($oldImageUrl !== $imageUrl) {
            $this->removeCommunityUpload($oldImageUrl);
        }

        if ($moderated['changed']) {
            $this->addFlash('info', 'Le texte a ete legerement modere avant enregistrement.');
        }

        $this->addFlash('success', 'Publication mise a jour.');

        return $this->redirectToRoute('app_community_posts');
    }

    #[Route('/posts/{id}/delete', name: 'app_community_post_delete', methods: ['POST'])]
    public function deletePost(Post $post, EntityManagerInterface $em): Response
    {
        if (!$this->canManagePost($post, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $imageUrl = $post->getImageUrl();
        $em->remove($post);
        $em->flush();
        $this->removeCommunityUpload($imageUrl);
        $this->addFlash('success', 'Publication supprimée.');

        return $this->redirectToRoute('app_community_posts');
    }

    // ========== GROUPS ==========
    #[Route('/groups', name: 'app_community_groups')]
    public function groups(GroupRepository $repo): Response
    {
        $user = $this->currentUser();
        $groups = $repo->findCommunityGroups();

        return $this->render('front/community/groups.html.twig', [
            'groups' => $groups,
            'pendingRequestGroupIds' => $repo->findPendingRequestGroupIdsForUser((int) $user->getId()),
        ]);
    }

    #[Route('/groups/new', name: 'app_community_group_new', methods: ['GET', 'POST'])]
    public function newGroup(
        Request $request,
        EntityManagerInterface $em,
        CommunityTextModerationService $textModerationService,
    ): Response
    {
        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $description = trim((string) $request->request->get('description', ''));

            if ($name === '' || $description === '') {
                $this->addFlash('danger', 'Le nom et la description du groupe sont requis.');

                return $this->render('front/community/group_form.html.twig');
            }

            $moderated = $textModerationService->moderate($description);
            $group = new Group();
            $group->setName($name);
            $group->setDescription($moderated['text']);
            $group->setGroupAdmin($this->currentUser());
            $group->setIsPrivate($request->request->getBoolean('is_private'));

            $em->persist($group);

            // Add creator as member
            $member = new GroupMember();
            $member->setGroup($group);
            $member->setUser($this->currentUser());
            $em->persist($member);
            $em->flush();

            if ($moderated['changed']) {
                $this->addFlash('info', 'La description a ete legerement moderee avant creation.');
            }

            $this->addFlash('success', 'Groupe créé !');
            return $this->redirectToRoute('app_community_group_show', ['id' => $group->getId()]);
        }

        return $this->render('front/community/group_form.html.twig');
    }

    #[Route('/groups/{id}', name: 'app_community_group_show', requirements: ['id' => '\d+'])]
    public function showGroup(Group $group, ThreadRepository $threadRepo): Response
    {
        $user = $this->currentUser();
        $groupId = (int) ($group->getId() ?? 0);
        $isOwner = $this->canManageGroup($group, $user);
        $isMember = $isOwner || $this->groupRepository->isMember($groupId, (int) $user->getId());
        $canViewContent = !$group->isPrivate() || $isMember || $this->isGranted('ROLE_ADMIN');
        $threads = $canViewContent ? $threadRepo->findByGroup($group) : [];

        return $this->render('front/community/group_show.html.twig', [
            'group' => $group,
            'threads' => $threads,
            'isOwner' => $isOwner,
            'isMember' => $isMember,
            'canViewContent' => $canViewContent,
            'canParticipate' => $isMember || $this->isGranted('ROLE_ADMIN'),
            'hasPendingRequest' => !$isOwner && !$isMember && $this->groupRepository->hasPendingRequest($groupId, (int) $user->getId()),
            'pendingRequests' => $isOwner ? $this->groupRepository->findPendingRequestsForGroup($groupId) : [],
        ]);
    }

    #[Route('/groups/{id}/join', name: 'app_community_group_join', methods: ['POST'])]
    public function joinGroup(Group $group, EntityManagerInterface $em): Response
    {
        $user = $this->currentUser();
        $groupId = (int) ($group->getId() ?? 0);
        $userId = (int) ($user->getId() ?? 0);

        if ($group->getGroupAdmin()?->getId() === $userId || $this->groupRepository->isMember($groupId, $userId)) {
            $this->addFlash('info', 'Vous etes deja membre de ce groupe.');

            return $this->redirectToRoute('app_community_group_show', ['id' => $group->getId()]);
        }

        if ($group->isPrivate() && !$this->isGranted('ROLE_ADMIN')) {
            if ($this->groupRepository->hasPendingRequest($groupId, $userId)) {
                $this->addFlash('info', 'Votre demande d\'acces est deja en attente.');
            } else {
                $this->groupRepository->requestJoin($groupId, $userId);
                $this->addFlash('success', 'Demande d\'acces envoyee a l\'administrateur du groupe.');
            }

            return $this->redirectToRoute('app_community_group_show', ['id' => $group->getId()]);
        }

        $member = new GroupMember();
        $member->setGroup($group);
        $member->setUser($user);
        $em->persist($member);
        $em->flush();

        $this->addFlash('success', 'Vous avez rejoint le groupe.');

        return $this->redirectToRoute('app_community_group_show', ['id' => $group->getId()]);
    }

    #[Route('/groups/{id}/requests/cancel', name: 'app_community_group_request_cancel', methods: ['POST'])]
    public function cancelGroupJoinRequest(Group $group): Response
    {
        $user = $this->currentUser();
        $this->groupRepository->cancelPendingRequest((int) ($group->getId() ?? 0), (int) ($user->getId() ?? 0));
        $this->addFlash('success', 'Demande d\'acces annulee.');

        return $this->redirectToRoute('app_community_group_show', ['id' => $group->getId()]);
    }

    #[Route('/groups/{groupId}/requests/{requestId}/approve', name: 'app_community_group_request_approve', methods: ['POST'])]
    public function approveGroupJoinRequest(int $groupId, int $requestId): Response
    {
        $group = $this->groupRepository->find($groupId);

        if (!$group instanceof Group) {
            throw $this->createNotFoundException();
        }

        if (!$this->canManageGroup($group, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $approved = $this->groupRepository->approveRequest($groupId, $requestId);
        $this->addFlash($approved === null ? 'info' : 'success', $approved === null ? 'Cette demande n\'est plus en attente.' : 'Demande approuvee.');

        return $this->redirectToRoute('app_community_group_show', ['id' => $groupId]);
    }

    #[Route('/groups/{groupId}/requests/{requestId}/reject', name: 'app_community_group_request_reject', methods: ['POST'])]
    public function rejectGroupJoinRequest(int $groupId, int $requestId): Response
    {
        $group = $this->groupRepository->find($groupId);

        if (!$group instanceof Group) {
            throw $this->createNotFoundException();
        }

        if (!$this->canManageGroup($group, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $this->groupRepository->rejectRequest($groupId, $requestId);
        $this->addFlash('success', 'Demande refusee.');

        return $this->redirectToRoute('app_community_group_show', ['id' => $groupId]);
    }

    #[Route('/groups/{id}/leave', name: 'app_community_group_leave', methods: ['POST'])]
    public function leaveGroup(Group $group, EntityManagerInterface $em): Response
    {
        $user = $this->currentUser();

        if ($group->getGroupAdmin()?->getId() === $user->getId()) {
            $this->addFlash('danger', 'Le createur du groupe ne peut pas le quitter.');

            return $this->redirectToRoute('app_community_group_show', ['id' => $group->getId()]);
        }

        $membership = $em->getRepository(GroupMember::class)->findOneBy(['group' => $group, 'user' => $user]);
        if ($membership instanceof GroupMember) {
            $em->remove($membership);
            $em->flush();
            $this->addFlash('success', 'Vous avez quitte le groupe.');
        }

        return $this->redirectToRoute('app_community_groups');
    }

    #[Route('/groups/{id}/delete', name: 'app_community_group_delete', methods: ['POST'])]
    public function deleteGroup(Group $group, EntityManagerInterface $em): Response
    {
        if (!$this->canManageGroup($group, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($group);
        $em->flush();
        $this->addFlash('success', 'Groupe supprime.');

        return $this->redirectToRoute('app_community_groups');
    }

    // ========== THREADS ==========
    #[Route('/groups/{id}/threads/new', name: 'app_community_thread_new', methods: ['POST'])]
    public function newThread(
        Group $group,
        Request $request,
        EntityManagerInterface $em,
        CommunityTextModerationService $textModerationService,
    ): Response
    {
        if (!$this->canParticipateInGroup($group, $this->currentUser())) {
            $this->addFlash('warning', 'Rejoignez ce groupe avant de publier une discussion.');

            return $this->redirectToRoute('app_community_group_show', ['id' => $group->getId()]);
        }

        $title = $this->sanitizeSubmittedTitle((string) $request->request->get('title', ''));
        $content = $this->sanitizeSubmittedBody((string) $request->request->get('content', ''));

        if (($validationError = $this->validateThreadInput($title, $content)) !== null) {
            $this->addFlash('danger', $validationError);

            return $this->redirectToRoute('app_community_group_show', ['id' => $group->getId()]);
        }

        $moderated = $textModerationService->moderate($content);
        $thread = new Thread();
        $thread->setGroup($group);
        $thread->setUser($this->currentUser());
        $thread->setTitle($title);
        $thread->setContent($moderated['text']);

        $em->persist($thread);
        $em->flush();

        if ($moderated['changed']) {
            $this->addFlash('info', 'Le texte a ete legerement modere avant publication.');
        }

        $this->addFlash('success', 'Discussion créée !');
        return $this->redirectToRoute('app_community_thread_show', ['id' => $thread->getId()]);
    }

    #[Route('/threads/{id}', name: 'app_community_thread_show')]
    public function showThread(Thread $thread, Request $request, CommentRepository $commentRepository): Response
    {
        $group = $thread->getGroup();
        if (!$group instanceof Group) {
            throw $this->createNotFoundException();
        }

        if (!$this->canAccessGroup($group, $this->currentUser())) {
            $this->addFlash('warning', 'Cette discussion appartient a un groupe prive que vous ne pouvez pas consulter.');

            return $this->redirectToRoute('app_community_group_show', ['id' => $group->getId()]);
        }

        return $this->render('front/community/thread_show.html.twig', [
            'thread' => $thread,
            'comments' => $commentRepository->findByThreadChronological($thread),
            'threadSummary' => $request->getSession()->get($this->threadSummaryKey((int) $thread->getId())),
            'replySuggestions' => $request->getSession()->get($this->threadSuggestionsKey((int) $thread->getId()), []),
            'canParticipate' => $this->canParticipateInGroup($group, $this->currentUser()),
        ]);
    }

    #[Route('/threads/{id}/comment', name: 'app_community_thread_comment', methods: ['POST'])]
    public function commentThread(
        Thread $thread,
        Request $request,
        EntityManagerInterface $em,
        CommunityTextModerationService $textModerationService,
    ): Response
    {
        if (!$this->canParticipateInGroup($thread->getGroup(), $this->currentUser())) {
            $this->addFlash('warning', 'Rejoignez ce groupe avant de commenter.');

            return $this->redirectToRoute('app_community_thread_show', ['id' => $thread->getId()]);
        }

        $content = $this->sanitizeSubmittedBody((string) $request->request->get('content', ''));
        if (($validationError = $this->validateCommentInput($content)) !== null) {
            $this->addFlash('danger', $validationError);

            return $this->redirectToRoute('app_community_thread_show', ['id' => $thread->getId()]);
        }

        $moderated = $textModerationService->moderate($content);
        $comment = new Comment();
        $comment->setThread($thread);
        $comment->setUser($this->currentUser());
        $comment->setContent($moderated['text']);

        $em->persist($comment);
        $em->flush();

        $this->resetThreadAiState($request, (int) $thread->getId());

        if ($moderated['changed']) {
            $this->addFlash('info', 'Le commentaire a ete legerement modere avant publication.');
        }

        return $this->redirectToRoute('app_community_thread_show', ['id' => $thread->getId()]);
    }

    #[Route('/threads/{id}/update', name: 'app_community_thread_update', methods: ['POST'])]
    public function updateThread(
        Thread $thread,
        Request $request,
        EntityManagerInterface $em,
        CommunityTextModerationService $textModerationService,
    ): Response
    {
        if (!$this->canManageThread($thread, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $title = $this->sanitizeSubmittedTitle((string) $request->request->get('title', ''));
        $content = $this->sanitizeSubmittedBody((string) $request->request->get('content', ''));

        if (($validationError = $this->validateThreadInput($title, $content)) !== null) {
            $this->addFlash('danger', $validationError);

            return $this->redirectToRoute('app_community_thread_show', ['id' => $thread->getId()]);
        }

        $moderated = $textModerationService->moderate($content);
        $thread->setTitle($title);
        $thread->setContent($moderated['text']);
        $em->flush();
        $this->resetThreadAiState($request, (int) $thread->getId());

        if ($moderated['changed']) {
            $this->addFlash('info', 'Le texte a ete legerement modere avant enregistrement.');
        }

        $this->addFlash('success', 'Discussion mise a jour.');

        return $this->redirectToRoute('app_community_thread_show', ['id' => $thread->getId()]);
    }

    #[Route('/threads/{id}/delete', name: 'app_community_thread_delete', methods: ['POST'])]
    public function deleteThread(Thread $thread, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->canManageThread($thread, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $groupId = $thread->getGroup()?->getId();
        $threadId = (int) $thread->getId();
        $em->remove($thread);
        $em->flush();
        $this->resetThreadAiState($request, $threadId);
        $this->addFlash('success', 'Discussion supprimee.');

        return $this->redirectToRoute('app_community_group_show', ['id' => $groupId]);
    }

    #[Route('/comments/{id}/update', name: 'app_community_comment_update', methods: ['POST'])]
    public function updateComment(
        Comment $comment,
        Request $request,
        EntityManagerInterface $em,
        CommunityTextModerationService $textModerationService,
    ): Response
    {
        if (!$this->canManageComment($comment, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $content = $this->sanitizeSubmittedBody((string) $request->request->get('content', ''));
        if (($validationError = $this->validateCommentInput($content)) !== null) {
            $this->addFlash('danger', $validationError);

            return $this->redirectToRoute('app_community_thread_show', ['id' => $comment->getThread()?->getId()]);
        }

        $moderated = $textModerationService->moderate($content);
        $comment->setContent($moderated['text']);
        $em->flush();
        $this->resetThreadAiState($request, (int) $comment->getThread()?->getId());

        if ($moderated['changed']) {
            $this->addFlash('info', 'Le commentaire a ete legerement modere avant enregistrement.');
        }

        $this->addFlash('success', 'Commentaire mis a jour.');

        return $this->redirectToRoute('app_community_thread_show', ['id' => $comment->getThread()?->getId()]);
    }

    #[Route('/comments/{id}/delete', name: 'app_community_comment_delete', methods: ['POST'])]
    public function deleteComment(Comment $comment, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->canManageComment($comment, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $threadId = (int) $comment->getThread()?->getId();
        $em->remove($comment);
        $em->flush();
        $this->resetThreadAiState($request, $threadId);
        $this->addFlash('success', 'Commentaire supprime.');

        return $this->redirectToRoute('app_community_thread_show', ['id' => $threadId]);
    }

    #[Route('/threads/{id}/summary', name: 'app_community_thread_summary', methods: ['POST'])]
    public function summarizeThread(
        Thread $thread,
        Request $request,
        CommentRepository $commentRepository,
        CommunityAiService $communityAiService,
    ): Response
    {
        if (!$this->canAccessGroup($thread->getGroup(), $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $summary = $communityAiService->summarizeThread($thread, $commentRepository->findByThreadChronological($thread));
        $request->getSession()->set($this->threadSummaryKey((int) $thread->getId()), $summary);
        $this->addFlash('success', 'Resume de discussion mis a jour.');

        return $this->redirectToRoute('app_community_thread_show', ['id' => $thread->getId()]);
    }

    #[Route('/threads/{id}/reply-suggestions', name: 'app_community_thread_reply_suggestions', methods: ['POST'])]
    public function threadReplySuggestions(
        Thread $thread,
        Request $request,
        CommentRepository $commentRepository,
        CommunityAiService $communityAiService,
    ): Response
    {
        if (!$this->canAccessGroup($thread->getGroup(), $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $suggestions = $communityAiService->suggestThreadReplies($thread, $commentRepository->findByThreadChronological($thread));
        $request->getSession()->set($this->threadSuggestionsKey((int) $thread->getId()), $suggestions);
        $this->addFlash('success', 'Suggestions de reponse generees.');

        return $this->redirectToRoute('app_community_thread_show', ['id' => $thread->getId()]);
    }

    // ========== EVENTS ==========
    #[Route('/events', name: 'app_community_events')]
    public function events(EventRepository $repo, Request $request): Response
    {
        $events = $repo->findUpcoming(50);
        $search = trim((string) $request->query->get('q', ''));
        $sort = trim((string) $request->query->get('sort', 'upcoming'));

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $events = array_values(array_filter($events, static function (Event $event) use ($needle): bool {
                return str_contains(mb_strtolower(trim((string) $event->getTitle())), $needle)
                    || str_contains(mb_strtolower(trim((string) $event->getDescription())), $needle);
            }));
        }

        usort($events, static function (Event $left, Event $right) use ($sort): int {
            return match ($sort) {
                'recent' => ($right->getEventDate()?->getTimestamp() ?? 0) <=> ($left->getEventDate()?->getTimestamp() ?? 0),
                'capacity' => ($right->getCapacity() <=> $left->getCapacity())
                    ?: (($left->getEventDate()?->getTimestamp() ?? 0) <=> ($right->getEventDate()?->getTimestamp() ?? 0)),
                default => ($left->getEventDate()?->getTimestamp() ?? 0) <=> ($right->getEventDate()?->getTimestamp() ?? 0),
            };
        });

        return $this->render('front/community/events.html.twig', [
            'events' => $events,
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    #[Route('/events/new', name: 'app_community_event_new', methods: ['GET', 'POST'])]
    public function newEvent(
        Request $request,
        EntityManagerInterface $em,
        CommunityTextModerationService $textModerationService,
    ): Response
    {
        if ($request->isMethod('POST')) {
            $title = trim((string) $request->request->get('title', ''));
            $description = trim((string) $request->request->get('description', ''));
            $eventDate = $this->normalizeEventDate((string) $request->request->get('event_date', ''));
            $capacity = max(1, (int) $request->request->get('capacity', 1));

            if ($title === '' || $description === '' || $eventDate === null) {
                $this->addFlash('danger', 'Le titre, la description et la date sont requis.');

                return $this->render('front/community/event_form.html.twig');
            }

            $moderated = $textModerationService->moderate($description);
            $event = new Event();
            $event->setTitle($title);
            $event->setDescription($moderated['text']);
            $event->setEventDate($eventDate);
            $event->setCapacity($capacity);
            $event->setCreatedBy($this->currentUser());

            $em->persist($event);
            $participant = new EventParticipant();
            $participant->setEvent($event);
            $participant->setUser($this->currentUser());
            $em->persist($participant);
            $em->flush();

            if ($moderated['changed']) {
                $this->addFlash('info', 'La description a ete legerement moderee avant creation.');
            }

            $this->addFlash('success', 'Événement créé !');

            return $this->redirectToRoute('app_community_event_show', ['id' => $event->getId()]);
        }

        return $this->render('front/community/event_form.html.twig');
    }

    #[Route('/events/{id}', name: 'app_community_event_show', requirements: ['id' => '\d+'])]
    public function showEvent(
        Event $event,
        Request $request,
        CommunityWeatherService $communityWeatherService,
        CommunityTicketService $communityTicketService,
    ): Response
    {
        $user = $this->currentUser();
        $participants = $event->getParticipants()->toArray();
        usort($participants, static function (EventParticipant $left, EventParticipant $right): int {
            return strcmp(
                trim((string) $left->getUser()?->getFullName()),
                trim((string) $right->getUser()?->getFullName()),
            );
        });

        return $this->render('front/community/event_show.html.twig', [
            'event' => $event,
            'participants' => $participants,
            'weather' => $communityWeatherService->forecastForEvent($event->getEventDate()),
            'isCreator' => $this->canManageEvent($event, $user),
            'isParticipant' => $event->hasParticipant($user),
            'isFull' => !$event->hasCapacity(),
            'eventAiOutput' => $request->getSession()->get($this->eventAiKey((int) $event->getId())),
            'eventAiMode' => $request->getSession()->get($this->eventAiModeKey((int) $event->getId()), 'summary'),
            'ticket' => $event->hasParticipant($user) ? $communityTicketService->buildTicket($event, $user) : null,
            'ticketValidation' => $request->getSession()->get($this->eventTicketValidationKey((int) $event->getId())),
        ]);
    }

    #[Route('/events/{id}/join', name: 'app_community_event_join', methods: ['POST'])]
    public function joinEvent(Event $event, EntityManagerInterface $em): Response
    {
        $user = $this->currentUser();

        if ($event->hasParticipant($user)) {
            $this->addFlash('info', 'Vous etes deja inscrit a cet evenement.');

            return $this->redirectToRoute('app_community_event_show', ['id' => $event->getId()]);
        }

        if (!$event->hasCapacity()) {
            $this->addFlash('danger', 'L\'événement est complet.');

            return $this->redirectToRoute('app_community_event_show', ['id' => $event->getId()]);
        }

        $participant = new EventParticipant();
        $participant->setEvent($event);
        $participant->setUser($user);
        $em->persist($participant);
        $em->flush();

        $this->addFlash('success', 'Inscription confirmée !');

        return $this->redirectToRoute('app_community_event_show', ['id' => $event->getId()]);
    }

    #[Route('/events/{id}/leave', name: 'app_community_event_leave', methods: ['POST'])]
    public function leaveEvent(Event $event, EntityManagerInterface $em): Response
    {
        $user = $this->currentUser();

        if ($event->getCreatedBy()?->getId() === $user->getId()) {
            $this->addFlash('danger', 'Le createur de l\'evenement ne peut pas le quitter.');

            return $this->redirectToRoute('app_community_event_show', ['id' => $event->getId()]);
        }

        $participant = $event->getParticipantForUser($user);
        if ($participant instanceof EventParticipant) {
            $em->remove($participant);
            $em->flush();
            $this->addFlash('success', 'Vous avez quitte l\'evenement.');
        }

        return $this->redirectToRoute('app_community_event_show', ['id' => $event->getId()]);
    }

    #[Route('/events/{id}/update', name: 'app_community_event_update', methods: ['POST'])]
    public function updateEvent(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        CommunityTextModerationService $textModerationService,
    ): Response
    {
        if (!$this->canManageEvent($event, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $title = trim((string) $request->request->get('title', ''));
        $description = trim((string) $request->request->get('description', ''));
        $eventDate = $this->normalizeEventDate((string) $request->request->get('event_date', ''));
        $capacity = max(1, (int) $request->request->get('capacity', 1));

        if ($title === '' || $description === '' || $eventDate === null) {
            $this->addFlash('danger', 'Le titre, la description et la date sont requis.');

            return $this->redirectToRoute('app_community_event_show', ['id' => $event->getId()]);
        }

        $moderated = $textModerationService->moderate($description);
        $event->setTitle($title);
        $event->setDescription($moderated['text']);
        $event->setEventDate($eventDate);
        $event->setCapacity($capacity);
        $em->flush();

        if ($moderated['changed']) {
            $this->addFlash('info', 'La description a ete legerement moderee avant enregistrement.');
        }

        $this->addFlash('success', 'Evenement mis a jour.');

        return $this->redirectToRoute('app_community_event_show', ['id' => $event->getId()]);
    }

    #[Route('/events/{id}/delete', name: 'app_community_event_delete', methods: ['POST'])]
    public function deleteEvent(Event $event, EntityManagerInterface $em): Response
    {
        if (!$this->canManageEvent($event, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($event);
        $em->flush();
        $this->addFlash('success', 'Evenement supprime.');

        return $this->redirectToRoute('app_community_events');
    }

    #[Route('/events/{id}/ai', name: 'app_community_event_ai', methods: ['POST'])]
    public function eventAi(
        Event $event,
        Request $request,
        CommunityAiService $communityAiService,
    ): Response
    {
        $mode = trim((string) $request->request->get('mode', 'summary'));
        $request->getSession()->set($this->eventAiKey((int) $event->getId()), $communityAiService->generateEventText($event, $mode));
        $request->getSession()->set($this->eventAiModeKey((int) $event->getId()), $mode);
        $this->addFlash('success', 'Assistant evenement mis a jour.');

        return $this->redirectToRoute('app_community_event_show', ['id' => $event->getId()]);
    }

    #[Route('/events/{id}/tickets/validate', name: 'app_community_event_ticket_validate', methods: ['POST'])]
    public function validateEventTicket(
        Event $event,
        Request $request,
        CommunityTicketService $communityTicketService,
    ): Response
    {
        if (!$this->canManageEvent($event, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        try {
            $payload = trim((string) $request->request->get('payload', ''));
            $image = $request->files->get('ticket_image');

            if ($payload === '' && $image instanceof UploadedFile) {
                $payload = $communityTicketService->decodeUploadedImage($image);
            }

            $result = $communityTicketService->validatePayloadForEvent($payload, $event);
            $request->getSession()->set($this->eventTicketValidationKey((int) $event->getId()), [
                'valid' => $result['valid'],
                'message' => $result['valid'] ? 'Ticket valide.' : 'Ticket invalide ou modifie.',
                'payload' => $result['payload'],
                'user_id' => $result['parsed']['u'],
            ]);

            $this->addFlash($result['valid'] ? 'success' : 'danger', $result['valid'] ? 'Ticket valide.' : 'Ticket invalide.');
        } catch (Throwable $exception) {
            $request->getSession()->set($this->eventTicketValidationKey((int) $event->getId()), [
                'valid' => false,
                'message' => $exception->getMessage(),
                'payload' => trim((string) $request->request->get('payload', '')),
                'user_id' => null,
            ]);

            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_community_event_show', ['id' => $event->getId()]);
    }

    #[Route('/events/{eventId}/participants/{userId}/remove', name: 'app_community_event_remove_participant', methods: ['POST'])]
    public function removeEventParticipant(int $eventId, int $userId, EntityManagerInterface $em, EventRepository $eventRepository): Response
    {
        $event = $eventRepository->find($eventId);
        $user = $em->getRepository(User::class)->find($userId);

        if (!$event instanceof Event || !$user instanceof User) {
            throw $this->createNotFoundException();
        }

        if (!$this->canManageEvent($event, $this->currentUser())) {
            throw $this->createAccessDeniedException();
        }

        if ($event->getCreatedBy()?->getId() === $user->getId()) {
            $this->addFlash('danger', 'Le createur ne peut pas etre retire de son evenement.');

            return $this->redirectToRoute('app_community_event_show', ['id' => $eventId]);
        }

        $participant = $event->getParticipantForUser($user);
        if ($participant instanceof EventParticipant) {
            $em->remove($participant);
            $em->flush();
            $this->addFlash('success', 'Participant retire de l\'evenement.');
        }

        return $this->redirectToRoute('app_community_event_show', ['id' => $eventId]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function canManagePost(Post $post, User $user): bool
    {
        return $post->getUser()?->getId() === $user->getId() || $this->isGranted('ROLE_ADMIN');
    }

    private function canManageGroup(Group $group, User $user): bool
    {
        return $group->getGroupAdmin()?->getId() === $user->getId() || $this->isGranted('ROLE_ADMIN');
    }

    private function canAccessGroup(Group $group, User $user): bool
    {
        return !$group->isPrivate()
            || $group->getGroupAdmin()?->getId() === $user->getId()
            || $this->groupRepository->isMember((int) ($group->getId() ?? 0), (int) ($user->getId() ?? 0))
            || $this->isGranted('ROLE_ADMIN');
    }

    private function canManageThread(Thread $thread, User $user): bool
    {
        return $thread->getUser()?->getId() === $user->getId() || $this->isGranted('ROLE_ADMIN');
    }

    private function canParticipateInGroup(Group $group, User $user): bool
    {
        return $group->getGroupAdmin()?->getId() === $user->getId()
            || $this->groupRepository->isMember((int) ($group->getId() ?? 0), (int) ($user->getId() ?? 0))
            || $this->isGranted('ROLE_ADMIN');
    }

    private function canManageComment(Comment $comment, User $user): bool
    {
        return $comment->getUser()?->getId() === $user->getId() || $this->isGranted('ROLE_ADMIN');
    }

    private function canManageEvent(Event $event, User $user): bool
    {
        return $event->getCreatedBy()?->getId() === $user->getId() || $this->isGranted('ROLE_ADMIN');
    }

    private function reactionError(Request $request, string $message): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'ok' => false,
                'message' => $message,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->addFlash('danger', $message);

        return $this->redirectToRoute('app_community_posts');
    }

    private function reactionStateResponse(Post $post, User $user): JsonResponse
    {
        $currentReaction = $post->getReactionTypeForUser($user);
        $meta = $currentReaction !== null ? (self::REACTION_META[$currentReaction] ?? null) : null;
        $caption = $post->getReactionsCount() > 0 ? $post->getReactionsCount().' reactions' : 'Pas encore de reaction';

        if ($meta !== null) {
            $caption .= ' · Vous avez choisi '.$meta['label'];
        }

        return new JsonResponse([
            'ok' => true,
            'totals' => $post->getReactionSummary(),
            'reactionsCount' => $post->getReactionsCount(),
            'myReaction' => $currentReaction,
            'currentLabel' => $meta['label'] ?? 'J\'aime',
            'currentEmoji' => $meta['emoji'] ?? (self::REACTION_META[PostReaction::TYPE_LIKE]['emoji'] ?? '👍'),
            'caption' => $caption,
        ]);
    }

    private function normalizeEventDate(string $raw): ?\DateTime
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d\TH:i', $raw);
        if ($date instanceof \DateTime) {
            return $date;
        }

        try {
            return new \DateTime($raw);
        } catch (Throwable) {
            return null;
        }
    }

    private function storePostUpload(?UploadedFile $file): ?string
    {
        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!$file->isValid()) {
            throw new \RuntimeException('Le televersement de l\'image a echoue.');
        }

        $mimeType = (string) ($file->getMimeType() ?? '');
        if (!str_starts_with($mimeType, 'image/')) {
            throw new \RuntimeException('Seules les images peuvent etre televersees.');
        }

        $directory = (string) $this->getParameter('kernel.project_dir').'/public/uploads/community/posts';
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de preparer le dossier des images communautaires.');
        }

        $extension = $file->guessExtension() ?: ($file->getClientOriginalExtension() ?: 'bin');
        $name = uniqid('community_post_', true).'.'.$extension;
        $file->move($directory, $name);

        return '/uploads/community/posts/'.$name;
    }

    private function removeCommunityUpload(?string $publicPath): void
    {
        if (!is_string($publicPath) || $publicPath === '' || !str_starts_with($publicPath, '/uploads/community/posts/')) {
            return;
        }

        $absolutePath = (string) $this->getParameter('kernel.project_dir').'/public'.$publicPath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function threadSummaryKey(int $threadId): string
    {
        return 'community.thread.summary.'.$threadId;
    }

    private function threadSuggestionsKey(int $threadId): string
    {
        return 'community.thread.suggestions.'.$threadId;
    }

    private function resetThreadAiState(Request $request, int $threadId): void
    {
        $session = $request->getSession();
        $session->remove($this->threadSummaryKey($threadId));
        $session->remove($this->threadSuggestionsKey($threadId));
    }

    private function validateThreadInput(string $title, string $content): ?string
    {
        if ($title === '' || $content === '') {
            return 'Le titre et le contenu de la discussion sont requis.';
        }

        if (mb_strlen($title) < self::THREAD_TITLE_MIN_LENGTH) {
            return 'Le titre doit contenir au moins 5 caracteres.';
        }

        if (mb_strlen($title) > self::THREAD_TITLE_MAX_LENGTH) {
            return 'Le titre est trop long.';
        }

        if (!$this->hasEnoughMeaningfulCharacters($title, 4)) {
            return 'Le titre doit contenir plus que quelques symboles ou caracteres repetes.';
        }

        if (mb_strlen($content) < self::THREAD_CONTENT_MIN_LENGTH) {
            return 'Le contenu de la discussion doit contenir au moins 15 caracteres utiles.';
        }

        if (mb_strlen($content) > self::THREAD_CONTENT_MAX_LENGTH) {
            return 'Le contenu de la discussion est trop long.';
        }

        if (!$this->hasEnoughMeaningfulCharacters($content, 10)) {
            return 'Le contenu de la discussion doit contenir un vrai message exploitable.';
        }

        if ($this->hasExcessiveRepeatedCharacters($title) || $this->hasExcessiveRepeatedCharacters($content)) {
            return 'Merci d eviter les suites de caracteres repetes dans votre discussion.';
        }

        return null;
    }

    private function validateCommentInput(string $content): ?string
    {
        if ($content === '') {
            return 'Le commentaire ne peut pas etre vide.';
        }

        if (mb_strlen($content) < self::COMMENT_CONTENT_MIN_LENGTH) {
            return 'Le commentaire est trop court pour etre utile.';
        }

        if (mb_strlen($content) > self::COMMENT_CONTENT_MAX_LENGTH) {
            return 'Le commentaire est trop long.';
        }

        if (!$this->hasEnoughMeaningfulCharacters($content, 3)) {
            return 'Le commentaire doit contenir plus que quelques symboles ou caracteres repetes.';
        }

        if ($this->hasExcessiveRepeatedCharacters($content)) {
            return 'Merci d eviter les suites de caracteres repetes dans votre commentaire.';
        }

        return null;
    }

    private function sanitizeSubmittedTitle(string $value): string
    {
        $value = strip_tags($value);
        $value = str_replace(["\r\n", "\r"], ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function sanitizeSubmittedBody(string $value): string
    {
        $value = strip_tags($value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = (string) preg_replace('/[ \t]+/u', ' ', $value);
        $value = (string) preg_replace('/\n{3,}/u', "\n\n", $value);

        return trim($value);
    }

    private function hasEnoughMeaningfulCharacters(string $value, int $minimum): bool
    {
        preg_match_all('/[\p{L}\p{N}]/u', $value, $matches);

        return count($matches[0] ?? []) >= $minimum;
    }

    private function hasExcessiveRepeatedCharacters(string $value): bool
    {
        return preg_match('/(.)\1{7,}/u', $value) === 1;
    }

    private function eventAiKey(int $eventId): string
    {
        return 'community.event.ai.'.$eventId;
    }

    private function eventAiModeKey(int $eventId): string
    {
        return 'community.event.ai.mode.'.$eventId;
    }

    private function eventTicketValidationKey(int $eventId): string
    {
        return 'community.event.ticket.validation.'.$eventId;
    }
}
