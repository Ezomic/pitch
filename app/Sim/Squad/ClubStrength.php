<?php

declare(strict_types=1);

namespace App\Sim\Squad;

/**
 * How good a club is, on the same 0..100 scale its players are rated on. Read
 * from the setup a club would field, so a Team and the manager's own Squad are
 * measured the same way and can be ranked against each other.
 */
final class ClubStrength
{
    /** Outfield quality is most of a side; the keeper and set pieces round it out. */
    private const float OUTFIELD_WEIGHT = 0.76;

    private const float KEEPING_WEIGHT = 0.16;

    private const float SET_PIECE_WEIGHT = 0.08;

    public function of(TeamSetup $setup): float
    {
        $ratings = [];
        foreach ($setup->bySlot as $attributes) {
            $ratings[] = ($attributes->vision + $attributes->passing + $attributes->dribbling
                + $attributes->finishing + $attributes->tackling + $attributes->pace) / 6;
        }

        $outfield = $ratings === [] ? 0.0 : array_sum($ratings) / count($ratings);

        return $outfield * self::OUTFIELD_WEIGHT
            + $setup->goalkeeping * self::KEEPING_WEIGHT
            + $setup->setPiece * self::SET_PIECE_WEIGHT;
    }
}
