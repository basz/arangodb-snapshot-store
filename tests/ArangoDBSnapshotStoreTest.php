<?php
/**
 * This file is part of the prooph/arangodb-snapshot-store.
 * (c) 2016-2016 prooph software GmbH <contact@prooph.de>
 * (c) 2016-2016 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\ArangoDB\SnapshotStore;

use ArangoDBClient\Connection;
use ArangoDBClient\Urls;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Prooph\ArangoDB\SnapshotStore\ArangoDBSnapshotStore;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Prooph\EventSourcing\Snapshot\Snapshot;
use ProophTest\EventSourcing\Mock\User;

class ArangoDBSnapshotStoreTest extends TestCase
{
    /**
     * @var ArangoDBSnapshotStore
     */
    private $snapshotStore;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @test
     */
    public function it_saves_and_reads()
    {
        $aggregateRoot = User::nameNew('Sandro');
        $aggregateType = AggregateType::fromAggregateRoot($aggregateRoot);

        $date = date('Y-m-d\TH:i:s.u');
        $now = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', $date, new DateTimeZone('UTC'));

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);
        $this->snapshotStore->save($snapshot);

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 2, $now);
        $this->snapshotStore->save($snapshot);

        $this->assertNull($this->snapshotStore->get($aggregateType, 'invalid'));

        $readSnapshot = $this->snapshotStore->get($aggregateType, 'id');
        $this->assertEquals($snapshot, $readSnapshot);
    }

    /**
     * @test
     */
    public function it_uses_custom_snapshot_table_map()
    {
        $aggregateType = AggregateType::fromAggregateRootClass(\stdClass::class);
        $aggregateRoot = new \stdClass();
        $aggregateRoot->foo = 'bar';

        $date = date('Y-m-d\TH:i:s.u');
        $now = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', $date, new DateTimeZone('UTC'));

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);

        $this->snapshotStore->save($snapshot);

        $response = $this->connection->get(Urls::URL_COLLECTION . '/bar/count')->getJson();
        $this->assertSame(1, $response['count'] ?? 0);

        $readSnapshot = $this->snapshotStore->get($aggregateType, 'id');
        $this->assertEquals($snapshot, $readSnapshot);
    }

    protected function setUp(): void
    {
        $this->connection = TestUtil::getClient();

        $this->connection->post(Urls::URL_COLLECTION, $this->connection->json_encode_wrapper(['name' => 'snapshots']));
        $this->connection->post(Urls::URL_COLLECTION, $this->connection->json_encode_wrapper(['name' => 'bar']));

        $this->snapshotStore = new ArangoDBSnapshotStore(
            $this->connection,
            [\stdClass::class => 'bar'],
            'snapshots'
        );
    }

    protected function tearDown(): void
    {
        $this->connection->delete(Urls::URL_COLLECTION . '/snapshots');
        $this->connection->delete(Urls::URL_COLLECTION . '/bar');
        unset($this->connection);
    }
}