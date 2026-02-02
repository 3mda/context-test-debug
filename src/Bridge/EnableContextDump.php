<?php

namespace ContextTest\Bridge;

use Attribute;

/**
 * Attribut PHP 8 pour forcer la génération du rapport de contexte sur une méthode de test.
 *
 * Ordre de priorité du décideur :
 * 1. Si TEST_FORCE_LOGS est défini → dump pour tous les tests.
 * 2. Si la méthode possède cet attribut → dump pour ce test uniquement.
 * 3. Sinon → pas de dump (sauf en cas d'échec, selon la config du run).
 */
#[Attribute(Attribute::TARGET_METHOD)]
class EnableContextDump
{
}
