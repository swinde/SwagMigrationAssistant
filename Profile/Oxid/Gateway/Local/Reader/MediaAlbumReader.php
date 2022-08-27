<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Gateway\Local\Reader;

use Doctrine\DBAL\Driver\ResultStatement;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Oxid\Gateway\Local\OxidLocalGateway;
use SwagMigrationAssistant\Profile\Oxid\OxidProfileInterface;

class MediaAlbumReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && $migrationContext->getGateway()->getName() === OxidLocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::MEDIA_FOLDER;
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        $this->setConnection($migrationContext);
        $fetchedAlbums = $this->fetchAlbums();

        $albums = $this->mapData(
            $fetchedAlbums,
            [],
            ['album']
        );

        $albums = $this->prepareMediaAlbums($albums);

        return $this->cleanupResultSet($albums);
    }

    private function fetchAlbums(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('s_media_album', 'album');
        $this->addTableSelection($query, 's_media_album', 'album');

        $query->leftJoin('album', 's_media_album_settings', 'setting', 'setting.albumID = album.id');
        $this->addTableSelection($query, 's_media_album_settings', 'setting');

        $query->orderBy('parentID');

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll();
    }

    private function prepareMediaAlbums(array $mediaAlbums): array
    {
        // represents the main language of the migrated shop
        $locale = $this->getDefaultShopLocale();

        $albums = [];
        foreach ($mediaAlbums as $key => $mediaAlbum) {
            if ($mediaAlbum['parentID'] !== null) {
                continue;
            }

            $mediaAlbum['_locale'] = $locale;
            $albums[] = [$mediaAlbum];
            unset($mediaAlbums[$key]);

            $childAlbums = $this->getChildAlbums($mediaAlbums, $mediaAlbum['id'], $locale);

            if (!empty($childAlbums)) {
                $albums[] = $childAlbums;
            }
        }
        unset($mediaAlbum);

        if (empty($albums)) {
            return $albums;
        }

        return \array_merge(...$albums);
    }

    private function getChildAlbums(array &$mediaAlbums, string $id, string $locale): array
    {
        $albums = [];
        foreach ($mediaAlbums as $key => $mediaAlbum) {
            if ($mediaAlbum['parentID'] !== $id) {
                continue;
            }

            $mediaAlbum['_locale'] = $locale;
            $albums[] = [$mediaAlbum];
            unset($mediaAlbums[$key]);

            $childAlbums = $this->getChildAlbums($mediaAlbums, $mediaAlbum['id'], $locale);

            if (!empty($childAlbums)) {
                $albums[] = $childAlbums;
            }
        }

        if (empty($albums)) {
            return $albums;
        }

        return \array_merge(...$albums);
    }
}
