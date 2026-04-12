<?php

declare(strict_types=1);

namespace ValueObject;

/**
 * Value Object représentant une adresse email normalisée et validée.
 *
 * Normalise en minuscules et supprime les espaces. Immuable.
 */
final class Email
{
    private string $value;

    /**
     * @param string $value Adresse email brute
     * @throws \InvalidArgumentException Si l'adresse email est invalide
     */
    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Email invalide : $value");
        }
        $this->value = $normalized;
    }

    /**
     * Retourne la valeur normalisée de l'email.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Compare deux adresses email.
     *
     * @param self $other Autre instance à comparer
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Retourne le domaine de l'adresse email.
     *
     * @return string
     */
    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }

    /**
     * Retourne la représentation string de l'email.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
