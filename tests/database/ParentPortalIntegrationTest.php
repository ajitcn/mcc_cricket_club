<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use RuntimeException;

final class ParentPortalIntegrationTest extends CIUnitTestCase
{
    protected $DBGroup = 'tests';
    private object $database;
    private int $parent;
    private int $otherParent;
    private int $player;
    private int $otherPlayer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        $this->parent = (int) $this->database->table('users')->where('email', 'test.mcc.parent@example.invalid')->get()->getRow('id');
        $this->otherParent = (int) $this->database->table('users')->where('email', 'test.mcc.player@example.invalid')->get()->getRow('id');
        $players = $this->database->table('players')->select('id')->orderBy('id')->get()->getResultArray();
        $this->player = (int) ($players[0]['id'] ?? 0);
        $this->otherPlayer = (int) ($players[1]['id'] ?? $this->player);
        $this->database->table('parent_player_links')->emptyTable();
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->parent], 'permissions' => ['parent_portal.view'], 'authorizedBranches' => ['1']]);
        \Config\Services::resetSingle('authorization');
    }

    protected function tearDown(): void
    {
        $this->database->table('parent_player_links')->emptyTable();
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testParentSeesOnlyPersistedLinkedChild(): void
    {
        $this->database->table('parent_player_links')->insert(['parent_user_id' => $this->parent, 'player_id' => $this->player, 'relationship_type' => 'guardian', 'created_at' => gmdate('Y-m-d H:i:s')]);
        $dashboard = service('parentPortal')->dashboard();
        self::assertCount(1, $dashboard['children']);
        self::assertSame($this->player, (int) $dashboard['children'][0]['id']);
        self::assertSame('Not available', $dashboard['visibility']['documents']);
    }

    public function testParentCannotAccessUnlinkedChild(): void
    {
        $this->database->table('parent_player_links')->insert(['parent_user_id' => $this->otherParent, 'player_id' => $this->otherPlayer, 'relationship_type' => 'guardian', 'created_at' => gmdate('Y-m-d H:i:s')]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not found.');
        service('parentPortal')->child($this->otherPlayer);
    }
}
