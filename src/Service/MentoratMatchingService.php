<?php

namespace App\Service;

use App\Entity\MentorshipRequest;
use App\Entity\User;

class MentoratMatchingService
{
    private const AUTO_ACCEPT_THRESHOLD = 70.0;

    /**
     * Calculate a matching score (0-100) between an entrepreneur and a mentor.
     * Based on: bio keywords, company sector, experience similarity.
     */
    public function calculateMatchScore(User $entrepreneur, User $mentor, ?string $projectSecteur = null): float
    {
        $score = 0.0;
        $factors = 0;

        // 1. Both have bios → keyword overlap (30 pts)
        $mentorBio = mb_strtolower($mentor->getBio() ?? '');
        $entrepreneurBio = mb_strtolower($entrepreneur->getBio() ?? '');
        if ($mentorBio && $entrepreneurBio) {
            $mentorWords = array_unique(preg_split('/\s+/', $mentorBio));
            $entWords = array_unique(preg_split('/\s+/', $entrepreneurBio));
            $common = array_intersect($mentorWords, $entWords);
            $stopWords = ['le', 'la', 'les', 'de', 'du', 'des', 'et', 'en', 'un', 'une', 'the', 'a', 'an', 'and', 'of', 'in', 'to', 'for', 'is', 'je', 'il', 'dans', 'avec', 'sur', 'par', 'au', 'aux'];
            $common = array_filter($common, fn($w) => mb_strlen($w) > 2 && !in_array($w, $stopWords));
            $overlap = count($common);
            $maxPossible = max(1, min(count($mentorWords), count($entWords)));
            $score += min(30, ($overlap / $maxPossible) * 100);
        }
        $factors += 30;

        // 2. Company name match (15 pts)
        if ($mentor->getCompanyName() && $entrepreneur->getCompanyName()) {
            $sim = 0;
            similar_text(
                mb_strtolower($mentor->getCompanyName()),
                mb_strtolower($entrepreneur->getCompanyName()),
                $sim
            );
            $score += ($sim / 100) * 15;
        }
        $factors += 15;

        // 3. Project sector matches mentor bio keywords (25 pts)
        if ($projectSecteur && $mentorBio) {
            $secteurWords = preg_split('/[\s,\-_]+/', mb_strtolower($projectSecteur));
            $found = 0;
            foreach ($secteurWords as $w) {
                if (mb_strlen($w) > 2 && str_contains($mentorBio, $w)) {
                    $found++;
                }
            }
            $score += min(25, ($found / max(1, count($secteurWords))) * 50);
        }
        $factors += 25;

        // 4. Both have LinkedIn → professional presence bonus (10 pts)
        if ($mentor->getLinkedinUrl() && $entrepreneur->getLinkedinUrl()) {
            $score += 10;
        } elseif ($mentor->getLinkedinUrl()) {
            $score += 5;
        }
        $factors += 10;

        // 5. Mentor is verified → trust bonus (10 pts)
        if ($mentor->isVerified()) {
            $score += 10;
        }
        $factors += 10;

        // 6. Baseline compatibility (10 pts for any active, non-banned mentor)
        if ($mentor->getIsActive() && !$mentor->getIsBanned()) {
            $score += 10;
        }
        $factors += 10;

        return round(min(100, ($score / $factors) * 100), 1);
    }

    /**
     * Determine if request should be auto-accepted based on match score.
     */
    public function shouldAutoAccept(float $matchScore): bool
    {
        return $matchScore >= self::AUTO_ACCEPT_THRESHOLD;
    }

    /**
     * Process a new request: calculate score and auto-accept if appropriate.
     */
    public function processRequest(MentorshipRequest $request, ?string $projectSecteur = null): void
    {
        $entrepreneur = $request->getEntrepreneur();
        $mentor = $request->getMentor();

        if (!$entrepreneur || !$mentor) {
            return;
        }

        $score = $this->calculateMatchScore($entrepreneur, $mentor, $projectSecteur);
        $request->setMatchScore($score);

        if ($this->shouldAutoAccept($score)) {
            $request->setAutoApproved(true);
            $request->setStatus(MentorshipRequest::STATUS_AUTO_ACCEPTED);
        }
    }
}
