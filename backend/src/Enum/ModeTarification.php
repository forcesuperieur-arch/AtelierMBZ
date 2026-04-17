<?php

namespace App\Enum;

enum ModeTarification: string
{
    case FORFAIT = 'forfait';
    case HORAIRE = 'horaire';
    case SUR_DEVIS = 'sur_devis';
}
