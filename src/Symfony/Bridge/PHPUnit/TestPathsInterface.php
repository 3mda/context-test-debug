<?php

namespace ContextTest\Symfony\Bridge\PHPUnit;

/**
 * Interface pour fournir les chemins de test au TestBootstrapper (projet hôte).
 */
interface TestPathsInterface
{
    public static function getProjectDir(): string;

    public static function getLogDir(): string;

    public static function getPhpErrorLogPath(): string;

    public static function getSymfonyLogPath(): string;

    public static function getSymfonyLogFilename(): string;

    public static function getContextJunitPath(): string;

    public static function getResultsJunitPath(): string;

    /**
     * @param string $phpErrorLog   Nom du fichier log erreurs PHP
     * @param string $symfonyLog    Nom du fichier log Symfony
     * @param string $contextJunit  Nom du fichier rapport contexte
     * @param string $symfonyLogKey Clé pour les logs Symfony
     */
    public static function configure(string $phpErrorLog, string $symfonyLog, string $contextJunit, string $symfonyLogKey = 'logs'): void;
}
