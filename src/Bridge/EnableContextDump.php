<?php

namespace ContextTest\Bridge;

use Attribute;

/**
 * Attribut PHP 8 pour forcer la génération du rapport de contexte sur une méthode de test.
 *
 * Ordre de priorité du décideur : (1) DEBUG falsy → (2) échec → (3) DEBUG truthy → (4) cet attribut.
 *
 * Comportement selon DEBUG :
 * - DEBUG falsy (0, false, no, off) : module désactivé, aucun dump (même sur échec).
 * - DEBUG absent : dump sur échec ou si la méthode a cet attribut.
 * - DEBUG truthy : dump pour tous les tests.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class EnableContextDump
{
}
