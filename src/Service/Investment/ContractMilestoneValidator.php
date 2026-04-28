<?php

namespace App\Service\Investment;

use App\Entity\ContractMilestone;

class ContractMilestoneValidator
{
    /**
     * @param list<ContractMilestone> $milestones
     */
    public function validate(array $milestones): bool
    {
        $count = count($milestones);
        if ($count < 2 || $count > 4) {
            throw new \InvalidArgumentException('Le nombre de jalons doit être compris entre 2 et 4');
        }

        $totalPercentage = 0.0;
        foreach ($milestones as $milestone) {
            $percentage = (float) $milestone->getPercentage();
            if ($percentage < 5.0 || $percentage > 80.0) {
                throw new \InvalidArgumentException('Chaque jalon doit représenter entre 5% et 80% du montant total');
            }

            $totalPercentage += $percentage;
        }

        if (abs($totalPercentage - 100.0) > 0.00001) {
            throw new \InvalidArgumentException('La somme des pourcentages des jalons doit être égale à 100%');
        }

        return true;
    }
}