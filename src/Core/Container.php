<?php

declare(strict_types=1);

namespace Core;

use RuntimeException;

/**
 * Conteneur d'injection de dépendances léger, compatible PSR-11.
 *
 * Supporte deux modes d'enregistrement :
 *  - bind()      : fabrique transient — nouvelle instance à chaque appel de get()
 *  - singleton() : fabrique singleton — instance créée une seule fois et réutilisée
 */
class Container
{
    /** @var array<string, callable> Factories enregistrées */
    private array $bindings = [];

    /** @var array<string, object> Instances singleton résolues */
    private array $instances = [];

    /** @var array<string, bool> Marqueurs de mode singleton */
    private array $singletons = [];

    /**
     * Enregistre une factory transient (nouvelle instance à chaque résolution).
     *
     * @param string   $abstract Identifiant de la dépendance (nom de classe ou interface)
     * @param callable $factory  Callable recevant le container en premier argument
     * @return void
     */
    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract]   = $factory;
        $this->singletons[$abstract] = false;
    }

    /**
     * Enregistre une factory singleton (instance partagée).
     *
     * @param string   $abstract Identifiant de la dépendance
     * @param callable $factory  Callable recevant le container en premier argument
     * @return void
     */
    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract]   = $factory;
        $this->singletons[$abstract] = true;
    }

    /**
     * Résout et retourne une instance de la dépendance demandée.
     *
     * @param string $abstract Identifiant de la dépendance
     * @return mixed Instance résolue
     * @throws RuntimeException Si le binding n'est pas enregistré
     */
    public function get(string $abstract): mixed
    {
        if (!$this->has($abstract)) {
            throw new RuntimeException(
                "Aucun binding enregistré pour : {$abstract}"
            );
        }

        // Singleton : retourner l'instance déjà résolue si elle existe
        if ($this->singletons[$abstract] && isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $instance = ($this->bindings[$abstract])($this);

        if ($this->singletons[$abstract]) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Vérifie si un binding est enregistré pour l'identifiant donné.
     *
     * @param string $abstract Identifiant de la dépendance
     * @return bool
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }
}
