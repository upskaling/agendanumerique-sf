<?php

declare(strict_types=1);

namespace App\Compil;

class CompilService
{
    public function __construct(
        private readonly CompilPwn $compilPwn,
        private readonly CompilCobaltPoitiers $compilCobaltPoitiers,
    ) {
    }

    public function compil(): void
    {
        // Lister toutes les sources de données dans src/Compil
        // Pour chaque source de données, appeler la méthode compil()

        $this->compilPwn->compil();
        $this->compilCobaltPoitiers->compil();
    }
}
