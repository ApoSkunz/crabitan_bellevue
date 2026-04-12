<?php

declare(strict_types=1);

namespace ValueObject;

/**
 * Value Object représentant une adresse postale validée.
 *
 * Champs basés sur la table `addresses` du schéma BDD.
 * Immutable via readonly properties (PHP 8.1+).
 */
final class Address
{
    /**
     * @param string $firstname Prénom du destinataire
     * @param string $lastname  Nom du destinataire
     * @param string $street    Rue et numéro
     * @param string $city      Ville
     * @param string $zipCode   Code postal
     * @param string $country   Code pays ISO 3166-1 alpha-2 (ex. FR, DE, BE)
     * @param string $civility  'M', 'F' ou 'other'
     * @param string $phone     Numéro de téléphone (optionnel)
     * @throws \InvalidArgumentException Si un champ obligatoire est vide ou invalide
     */
    public function __construct(
        public readonly string $firstname,
        public readonly string $lastname,
        public readonly string $street,
        public readonly string $city,
        public readonly string $zipCode,
        public readonly string $country,
        public readonly string $civility = 'other',
        public readonly string $phone = ''
    ) {
        $this->validate();
    }

    /**
     * Valide les champs de l'adresse.
     *
     * @return void
     * @throws \InvalidArgumentException Si un champ est invalide
     */
    private function validate(): void
    {
        if (trim($this->firstname) === '') {
            throw new \InvalidArgumentException('Le prénom est obligatoire.');
        }
        if (trim($this->lastname) === '') {
            throw new \InvalidArgumentException('Le nom est obligatoire.');
        }
        if (trim($this->street) === '') {
            throw new \InvalidArgumentException('La rue est obligatoire.');
        }
        if (trim($this->city) === '') {
            throw new \InvalidArgumentException('La ville est obligatoire.');
        }
        if (trim($this->zipCode) === '') {
            throw new \InvalidArgumentException('Le code postal est obligatoire.');
        }
        if (!preg_match('/^[A-Za-z0-9\s\-]{2,10}$/', $this->zipCode)) {
            throw new \InvalidArgumentException("Code postal invalide : {$this->zipCode}");
        }
        if (trim($this->country) === '') {
            throw new \InvalidArgumentException('Le pays est obligatoire.');
        }
        if (!preg_match('/^[A-Z]{2}$/', strtoupper($this->country))) {
            throw new \InvalidArgumentException("Code pays invalide (ISO 3166-1 alpha-2 attendu) : {$this->country}");
        }
        if (!in_array($this->civility, ['M', 'F', 'other'], true)) {
            throw new \InvalidArgumentException("Civilité invalide : {$this->civility}");
        }
    }

    /**
     * Retourne l'adresse sous forme de tableau associatif.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'firstname' => $this->firstname,
            'lastname'  => $this->lastname,
            'street'    => $this->street,
            'city'      => $this->city,
            'zip_code'  => $this->zipCode,
            'country'   => $this->country,
            'civility'  => $this->civility,
            'phone'     => $this->phone,
        ];
    }

    /**
     * Compare deux adresses.
     *
     * @param self $other Autre instance à comparer
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->firstname === $other->firstname
            && $this->lastname  === $other->lastname
            && $this->street    === $other->street
            && $this->city      === $other->city
            && $this->zipCode   === $other->zipCode
            && $this->country   === $other->country
            && $this->civility  === $other->civility
            && $this->phone     === $other->phone;
    }

    /**
     * Retourne une représentation lisible de l'adresse.
     *
     * @return string
     */
    public function __toString(): string
    {
        return "{$this->firstname} {$this->lastname}, {$this->street}, {$this->zipCode} {$this->city}, {$this->country}";
    }
}
