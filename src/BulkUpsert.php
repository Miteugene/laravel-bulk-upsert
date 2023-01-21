<?php

namespace Lapaliv\BulkUpsert;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\BulkUpsertContract;
use Lapaliv\BulkUpsert\Converters\ArrayToCollectionConverter;
use Lapaliv\BulkUpsert\Entities\UpsertConfig;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;
use Lapaliv\BulkUpsert\Features\GetEloquentNativeEventNameFeature;
use Lapaliv\BulkUpsert\Features\SeparateIterableRowsFeature;
use Lapaliv\BulkUpsert\Scenarios\UpsertScenario;
use Lapaliv\BulkUpsert\Traits\BulkChunkTrait;
use Lapaliv\BulkUpsert\Traits\BulkEventsTrait;
use Lapaliv\BulkUpsert\Traits\BulkInsertTrait;
use Lapaliv\BulkUpsert\Traits\BulkSaveTrait;
use Lapaliv\BulkUpsert\Traits\BulkSelectTrait;
use Lapaliv\BulkUpsert\Traits\BulkUpdateTrait;

class BulkUpsert implements BulkUpsertContract
{
    use BulkChunkTrait, BulkSelectTrait, BulkEventsTrait, BulkSaveTrait, BulkInsertTrait, BulkUpdateTrait;

    public function __construct(
        private GetBulkModelFeature $getBulkModelFeature,
        private GetEloquentNativeEventNameFeature $getEloquentNativeEventNameFeature,
        private SeparateIterableRowsFeature $separateIterableRowsFeature,
        private ArrayToCollectionConverter $arrayToCollectionConverter,
        private UpsertScenario $scenario,
    )
    {
        $this->setEvents($this->getDefaultEvents());
    }

    public function upsert(
        string|BulkModel $model,
        iterable $rows,
        array $uniqueAttributes,
        ?array $updateAttributes = null,
    ): void
    {
        $model = $this->getBulkModelFeature->handle($model);
        $this->scenario->setEloquent($model);
        $config = $this->getConfig($model, $uniqueAttributes, $updateAttributes);

        $this->separateIterableRowsFeature->handle(
            $this->chunkSize,
            $rows,
            function (array $chunk) use ($model, $config): void {
                $collection = $this->arrayToCollectionConverter->handle($model, $chunk);
                $collection = $this->chunkCallback?->handle($collection) ?? $collection;

                $this->scenario
                    ->push($model, $collection, $config)
                    ->insert($config)
                    ->update($config);
            }
        );

        $this->scenario
            ->insert($config, force: true)
            ->update($config, force: true);
    }

    protected function getDefaultEvents(): array
    {
        return [
            BulkEventEnum::CREATING,
            BulkEventEnum::CREATED,
            BulkEventEnum::UPDATING,
            BulkEventEnum::UPDATED,
            BulkEventEnum::SAVING,
            BulkEventEnum::SAVED,
        ];
    }

    private function getConfig(BulkModel $eloquent, array $uniqueAttributes, ?array $updateAttributes): UpsertConfig
    {
        return new UpsertConfig(
            events: $this->getIntersectEventsWithDispatcher($eloquent, $this->getEloquentNativeEventNameFeature),
            uniqueAttributes: $uniqueAttributes,
            updateAttributes: $updateAttributes,
            selectColumns: $this->getSelectColumns($uniqueAttributes, $updateAttributes),
            chunkSize: $this->chunkSize,
            chunkCallback: $this->chunkCallback,
            creatingCallback: $this->creatingCallback,
            createdCallback: $this->createdCallback,
            updatingCallback: $this->updatingCallback,
            updatedCallback: $this->updatedCallback,
            savingCallback: $this->savingCallback,
            savedCallback: $this->savedCallback
        );
    }
}
