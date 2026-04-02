<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Service\AccountService;
use Service\MailService;
use Model\AccountModel;
use Core\Exception\HttpException;

/**
 * Tests unitaires de AccountService — feature changement d'email.
 *
 * Pattern : mock AccountModel + MailService → pas de BDD nécessaire.
 */
class AccountServiceEmailChangeTest extends TestCase
{
    private AccountService $service;

    /** @var AccountModel&MockObject */
    private AccountModel $accountModel;

    /** @var MailService&MockObject */
    private MailService $mailService;

    protected function setUp(): void
    {
        $this->accountModel = $this->createMock(AccountModel::class);
        $this->mailService  = $this->createMock(MailService::class);

        $this->service = new AccountService(
            $this->accountModel,
            $this->mailService
        );
    }

    // ----------------------------------------------------------------
    // requestEmailChange — cas nominaux
    // ----------------------------------------------------------------

    /**
     * Vérifie que requestEmailChange génère un token haché, persiste les données
     * et envoie les deux emails (confirmation + notification).
     */
    public function testRequestEmailChangeGeneratesToken(): void
    {
        $userId   = 42;
        $password = 'Password123!';
        $hashed   = password_hash($password, PASSWORD_BCRYPT);
        $newEmail = 'nouveau@example.com';
        $oldEmail = 'ancien@example.com';

        $this->accountModel
            ->method('findById')
            ->with($userId)
            ->willReturn([
                'id'       => $userId,
                'email'    => $oldEmail,
                'password' => $hashed,
                'lang'     => 'fr',
                'firstname' => 'Jean',
                'lastname'  => 'Dupont',
                'company_name' => null,
            ]);

        // La nouvelle adresse n'est pas déjà prise
        $this->accountModel
            ->method('findByEmail')
            ->with($newEmail)
            ->willReturn(false);

        // Rate limit : compteur à 0
        $this->accountModel
            ->method('countEmailChangeRequestsLast24h')
            ->with($userId)
            ->willReturn(0);

        // On attend que saveEmailChangeToken soit appelé
        $this->accountModel
            ->expects($this->once())
            ->method('saveEmailChangeToken');

        // Confirmation → ancienne adresse (identité prouvée)
        $this->mailService
            ->expects($this->once())
            ->method('sendEmailChangeConfirmation')
            ->with(
                $this->equalTo($oldEmail),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->equalTo($newEmail)
            );

        // Notification → nouvelle adresse (information simple)
        $this->mailService
            ->expects($this->once())
            ->method('sendEmailChangeNotification')
            ->with(
                $this->equalTo($newEmail),
                $this->anything()
            );

        // On attend que l'audit soit loggué
        $this->accountModel
            ->expects($this->once())
            ->method('logAuditEvent');

        $this->service->requestEmailChange($userId, $newEmail, $password, '127.0.0.1');
    }

    /**
     * Vérifie que requestEmailChange lève une HttpException si le mot de passe est incorrect.
     */
    public function testRequestEmailChangeRejectsWrongPassword(): void
    {
        $this->expectException(HttpException::class);

        $this->accountModel
            ->method('findById')
            ->willReturn([
                'id'       => 1,
                'email'    => 'old@example.com',
                'password' => password_hash('correct', PASSWORD_BCRYPT),
                'lang'     => 'fr',
            ]);

        $this->accountModel
            ->method('findByEmail')
            ->willReturn(false);

        $this->accountModel
            ->method('countEmailChangeRequestsLast24h')
            ->willReturn(0);

        $this->service->requestEmailChange(1, 'new@example.com', 'wrong_password', '127.0.0.1');
    }

    /**
     * Vérifie que requestEmailChange lève une HttpException si le rate limit est atteint (>= 3).
     */
    public function testRequestEmailChangeRejectsWhenRateLimited(): void
    {
        $this->expectException(HttpException::class);

        $this->accountModel
            ->method('findById')
            ->willReturn([
                'id'       => 1,
                'email'    => 'old@example.com',
                'password' => password_hash('Password123!', PASSWORD_BCRYPT),
                'lang'     => 'fr',
            ]);

        $this->accountModel
            ->method('findByEmail')
            ->willReturn(false);

        // 3 demandes déjà faites → rate limit atteint
        $this->accountModel
            ->method('countEmailChangeRequestsLast24h')
            ->willReturn(3);

        $this->service->requestEmailChange(1, 'new@example.com', 'Password123!', '127.0.0.1');
    }

    /**
     * Vérifie que requestEmailChange lève une HttpException si la nouvelle adresse est déjà prise.
     */
    public function testRequestEmailChangeRejectsAlreadyTakenEmail(): void
    {
        $this->expectException(HttpException::class);

        $this->accountModel
            ->method('findById')
            ->willReturn([
                'id'       => 1,
                'email'    => 'old@example.com',
                'password' => password_hash('Password123!', PASSWORD_BCRYPT),
                'lang'     => 'fr',
            ]);

        // La nouvelle adresse est déjà prise
        $this->accountModel
            ->method('findByEmail')
            ->willReturn(['id' => 99]);

        $this->accountModel
            ->method('countEmailChangeRequestsLast24h')
            ->willReturn(0);

        $this->service->requestEmailChange(1, 'taken@example.com', 'Password123!', '127.0.0.1');
    }

    // ----------------------------------------------------------------
    // confirmEmailChange — cas nominaux
    // ----------------------------------------------------------------

    /**
     * Vérifie que confirmEmailChange avec un token valide met à jour l'email,
     * révoque toutes les sessions et log l'audit.
     */
    public function testConfirmEmailChangeWithValidToken(): void
    {
        $rawToken   = str_repeat('a', 64);
        $hashedToken = hash('sha256', $rawToken);
        $userId     = 42;

        $this->accountModel
            ->method('findByEmailChangeToken')
            ->with($hashedToken)
            ->willReturn([
                'id'                      => $userId,
                'email'                   => 'old@example.com',
                'email_change_new_email'  => 'new@example.com',
                'email_change_expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'email_change_used_at'    => null,
                'lang'                    => 'fr',
            ]);

        $this->accountModel
            ->expects($this->once())
            ->method('applyEmailChange')
            ->with($userId, 'new@example.com');

        $this->accountModel
            ->expects($this->once())
            ->method('revokeAllSessions')
            ->with($userId);

        $this->accountModel
            ->expects($this->once())
            ->method('logAuditEvent');

        $this->service->confirmEmailChange($rawToken);
    }

    /**
     * Vérifie que confirmEmailChange lève une HttpException pour un token expiré.
     */
    public function testConfirmEmailChangeWithExpiredToken(): void
    {
        $this->expectException(HttpException::class);

        $rawToken    = str_repeat('b', 64);
        $hashedToken = hash('sha256', $rawToken);

        $this->accountModel
            ->method('findByEmailChangeToken')
            ->with($hashedToken)
            ->willReturn([
                'id'                      => 1,
                'email'                   => 'old@example.com',
                'email_change_new_email'  => 'new@example.com',
                'email_change_expires_at' => date('Y-m-d H:i:s', time() - 1), // expiré
                'email_change_used_at'    => null,
                'lang'                    => 'fr',
            ]);

        $this->service->confirmEmailChange($rawToken);
    }

    /**
     * Vérifie que confirmEmailChange lève une HttpException pour un token déjà utilisé.
     */
    public function testConfirmEmailChangeAlreadyUsedToken(): void
    {
        $this->expectException(HttpException::class);

        $rawToken    = str_repeat('c', 64);
        $hashedToken = hash('sha256', $rawToken);

        $this->accountModel
            ->method('findByEmailChangeToken')
            ->with($hashedToken)
            ->willReturn([
                'id'                      => 1,
                'email'                   => 'old@example.com',
                'email_change_new_email'  => 'new@example.com',
                'email_change_expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'email_change_used_at'    => date('Y-m-d H:i:s', time() - 60), // déjà utilisé
                'lang'                    => 'fr',
            ]);

        $this->service->confirmEmailChange($rawToken);
    }

    /**
     * Vérifie que confirmEmailChange lève une HttpException pour un token introuvable.
     */
    public function testConfirmEmailChangeWithUnknownToken(): void
    {
        $this->expectException(HttpException::class);

        $rawToken    = str_repeat('d', 64);
        $hashedToken = hash('sha256', $rawToken);

        $this->accountModel
            ->method('findByEmailChangeToken')
            ->with($hashedToken)
            ->willReturn(false);

        $this->service->confirmEmailChange($rawToken);
    }
}
