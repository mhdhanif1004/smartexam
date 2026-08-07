<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([
            'single_choice',
            'multiple_choice',
            'true_false',
            'matching',
            'essay',
        ]);

        return array_merge($this->payloadFor($type), [
            'subject_id' => Subject::factory(),
            'type' => $type,
            'question_text' => rtrim(fake()->sentence(), '.').'?',
            'score_weight' => fake()->randomElement([5, 10, 15, 20]),
            'is_active' => true,
        ]);
    }

    /**
     * Build options/answer_key based on the question type.
     *
     * @return array<string, mixed>
     */
    private function payloadFor(string $type): array
    {
        return match ($type) {
            'single_choice' => [
                'options' => $this->options(4),
                'answer_key' => 'A',
            ],
            'multiple_choice' => [
                'options' => $this->options(5),
                'answer_key' => ['A', 'C'],
            ],
            'true_false' => [
                'options' => null,
                'answer_key' => fake()->boolean(),
            ],
            'matching' => [
                'options' => [
                    'left' => [fake()->sentence(2), fake()->sentence(2), fake()->sentence(2)],
                    'right' => [fake()->sentence(2), fake()->sentence(2), fake()->sentence(2)],
                ],
                'answer_key' => ['A' => '1', 'B' => '3', 'C' => '2'],
            ],
            'essay' => [
                'options' => null,
                'answer_key' => fake()->sentence(8),
            ],
        };
    }

    /**
     * Generate labeled options (A, B, C, ...).
     *
     * @return array<string, string>
     */
    private function options(int $count): array
    {
        $keys = array_map(fn (int $i) => chr(ord('A') + $i), range(0, $count - 1));

        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => fake()->sentence(3)])
            ->all();
    }
}
