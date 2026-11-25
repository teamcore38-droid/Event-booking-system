<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once __DIR__.'/../app/actions.php';

final class AccessTest extends TestCase
{
    protected function setUp(): void
    {
        $_POST = [];
        $_SESSION = ['csrf' => 'test-token', 'user' => ['role' => 'staff']];
    }

    public function testStaffCannotAccessAdministratorOperations(): void
    {
        $this->expectException(DomainException::class);
        needRole('admin');
    }

    public function testStateChangesRequireMatchingFormToken(): void
    {
        $_POST['csrf'] = 'stale';
        $this->expectExceptionMessage('Your form expired');
        action('paid');
    }

    public function testParticipantsCannotMarkPaymentsReceived(): void
    {
        $_SESSION['user']['role'] = 'participant';
        $_POST['csrf'] = $_SESSION['csrf'];
        $this->expectException(DomainException::class);
        action('paid');
    }
}