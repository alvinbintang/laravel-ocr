<?php

namespace Database\Factories;

use App\Models\OcrResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OcrResult>
 */
class OcrResultFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OcrResult::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'filename' => $this->faker->word() . '.pdf',
            'document_type' => $this->faker->randomElement(['RAB', 'RKA']),
            'text' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['processing', 'ready', 'completed', 'error']),
            'image_path' => '/storage/ocr/' . $this->faker->word() . '_page_1.png',
            'image_paths' => json_encode([
                '/storage/ocr/' . $this->faker->word() . '_page_1.png'
            ]),
            'ocr_results' => json_encode([
                'confidence' => $this->faker->randomFloat(2, 80, 99),
                'text_blocks' => [
                    [
                        'text' => $this->faker->sentence(),
                        'confidence' => $this->faker->randomFloat(2, 80, 99),
                        'bbox' => [
                            'x' => $this->faker->numberBetween(0, 500),
                            'y' => $this->faker->numberBetween(0, 500),
                            'width' => $this->faker->numberBetween(100, 300),
                            'height' => $this->faker->numberBetween(20, 50)
                        ]
                    ]
                ]
            ]),
            'selected_regions' => json_encode([
                [
                    'x' => $this->faker->numberBetween(0, 500),
                    'y' => $this->faker->numberBetween(0, 500),
                    'width' => $this->faker->numberBetween(100, 300),
                    'height' => $this->faker->numberBetween(50, 150)
                ]
            ]),
            'cropped_images' => json_encode([
                '/storage/ocr/cropped/' . $this->faker->word() . '_crop_1.png'
            ]),
            'page_rotations' => json_encode([
                '1' => $this->faker->randomElement([0, 90, 180, 270])
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the OCR result is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'text' => null,
            'ocr_results' => null,
        ]);
    }

    /**
     * Indicate that the OCR result is ready for processing.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ready',
            'text' => null,
            'ocr_results' => null,
        ]);
    }

    /**
     * Indicate that the OCR result is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'text' => $this->faker->paragraph(10),
            'ocr_results' => json_encode([
                'confidence' => $this->faker->randomFloat(2, 85, 99),
                'text_blocks' => [
                    [
                        'text' => $this->faker->sentence(),
                        'confidence' => $this->faker->randomFloat(2, 85, 99),
                        'bbox' => [
                            'x' => $this->faker->numberBetween(0, 500),
                            'y' => $this->faker->numberBetween(0, 500),
                            'width' => $this->faker->numberBetween(100, 300),
                            'height' => $this->faker->numberBetween(20, 50)
                        ]
                    ]
                ]
            ]),
        ]);
    }

    /**
     * Indicate that the OCR result has an error.
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'text' => null,
            'ocr_results' => null,
        ]);
    }
}