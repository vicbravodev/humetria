<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Enums\CutAttribute;
use App\Modules\Engagement\Enums\ItemBlock;
use App\Modules\Engagement\Enums\ItemScale;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Audience\Segment;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Thresholds;

/**
 * What a closed survey says, for the company and for each cut. The responses are read once; every figure goes through
 * the minimums of Thresholds. A survey that is not closed has no results: nothing is read, because watching a group grow
 * from five to six would point at the sixth person.
 */
final class SurveyResults
{
    /** @var list<ResponseRow>|null */
    private ?array $rows = null;

    private ?GroupSummary $company = null;

    private ?self $previous = null;

    private bool $previousLoaded = false;

    private function __construct(private readonly Survey $survey, private readonly Thresholds $thresholds) {}

    public static function for(Survey $survey): self
    {
        return new self($survey, resolve(Thresholds::class));
    }

    public function survey(): Survey
    {
        return $this->survey;
    }

    public function available(): bool
    {
        return $this->survey->status === SurveyStatus::Closed;
    }

    /** The whole company, however few answered: what is under the minimum is hidden item by item. */
    public function company(): GroupSummary
    {
        return $this->company ??= $this->summarize($this->rows());
    }

    /**
     * The groups of the cut that may be shown, with their summaries, and whether any was left out.
     *
     * @return array{groups: array<string, GroupSummary>, hasHidden: bool}
     */
    public function cut(CutAttribute $by): array
    {
        $cut = CutReport::of($by, $this->rows(), $this->thresholds);

        return ['groups' => array_map($this->summarize(...), $cut->visible), 'hasHidden' => $cut->hasHidden];
    }

    /** The survey of the same kind closed just before this one, with its own results; `null` in the first wave. */
    public function previous(): ?self
    {
        if (! $this->previousLoaded) {
            $this->previousLoaded = true;
            $previous = Survey::query()
                ->where('kind', $this->survey->kind)
                ->where('status', SurveyStatus::Closed)
                ->where('closed_at', '<', $this->survey->closed_at)
                ->whereKeyNot($this->survey->id)
                ->latest('closed_at')
                ->first();
            $this->previous = $previous === null ? null : new self($previous, $this->thresholds);
        }

        return $this->previous;
    }

    /** Whether a question was asked with the very same wording in this survey and in the other. */
    public function sameWording(string $key, self $other): bool
    {
        $mine = $this->refOf($key);

        return $mine !== null && $mine === $other->refOf($key);
    }

    /**
     * @param  list<ResponseRow>  $rows
     */
    private function summarize(array $rows): GroupSummary
    {
        $byKey = [];
        foreach ($this->questions() as $item) {
            $byKey[$item->key] = ItemTally::of($item, $rows, $this->thresholds);
        }

        $companyItems = array_values(array_filter($byKey, fn (ItemResult $result): bool => ItemBank::find($result->key)->block === ItemBlock::Company));

        return new GroupSummary(
            count($rows),
            $byKey,
            Indices::engagement($byKey),
            Indices::recommendation($rows, $this->thresholds),
            DriverScores::of($byKey),
            Highlights::pick($companyItems, 3),
        );
    }

    /** @return list<BankItem> the agreement questions about the company that this survey asked */
    private function questions(): array
    {
        $asked = array_map(fn (string $ref): string => explode('@', $ref)[0], $this->survey->items);

        return array_values(array_filter(
            ItemBank::all(),
            fn (BankItem $item): bool => $item->scale === ItemScale::Agreement && $item->block !== ItemBlock::Manager && in_array($item->key, $asked, true),
        ));
    }

    private function refOf(string $key): ?string
    {
        foreach ($this->survey->items as $ref) {
            if (explode('@', $ref)[0] === $key) {
                return $ref;
            }
        }

        return null;
    }

    /** @return list<ResponseRow> */
    private function rows(): array
    {
        if ($this->rows === null) {
            $this->rows = [];
            if ($this->available()) {
                foreach (Response::query()->where('survey_id', $this->survey->id)->get(['work_center_id', 'area_id', 'tenure_band', 'manager_group_id', 'answers']) as $response) {
                    $this->rows[] = new ResponseRow(new Segment($response->work_center_id, $response->area_id, $response->tenure_band, $response->manager_group_id), $response->answers);
                }
            }
        }

        return $this->rows;
    }
}
