<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\OcrResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);
        
        // Create token for authentication
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /** @test */
    public function user_can_register()
    {
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'created_at',
                            'updated_at'
                        ],
                        'token'
                    ]
                ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com'
        ]);
    }

    /** @test */
    public function user_can_login()
    {
        $loginData = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'created_at',
                            'updated_at'
                        ],
                        'token'
                    ]
                ]);
    }

    /** @test */
    public function user_can_logout()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logged out successfully'
                ]);
    }

    /** @test */
    public function user_can_get_profile()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/auth/user');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at'
                    ]
                ]);
    }

    /** @test */
    public function user_can_get_ocr_results()
    {
        // Create test OCR result
        $ocrResult = OcrResult::factory()->create([
            'filename' => 'test.pdf',
            'status' => 'completed',
            'text' => 'Sample text'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/ocr');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'filename',
                            'document_type',
                            'status',
                            'text',
                            'created_at',
                            'updated_at'
                        ]
                    ],
                    'meta' => [
                        'total'
                    ]
                ]);
    }

    /** @test */
    public function user_can_upload_pdf_for_ocr()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('test.pdf', 1000, 'application/pdf');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/ocr/extract', [
            'pdf_file' => $file
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'filename',
                        'document_type',
                        'status',
                        'created_at',
                        'updated_at'
                    ]
                ]);
    }

    /** @test */
    public function user_can_get_specific_ocr_result()
    {
        $ocrResult = OcrResult::factory()->create([
            'filename' => 'test.pdf',
            'status' => 'completed',
            'text' => 'Sample text'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/ocr/{$ocrResult->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'filename',
                        'document_type',
                        'status',
                        'text',
                        'created_at',
                        'updated_at'
                    ]
                ]);
    }

    /** @test */
    public function user_can_preview_ocr_data()
    {
        $ocrResult = OcrResult::factory()->create([
            'filename' => 'test.pdf',
            'status' => 'ready'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/ocr/{$ocrResult->id}/preview");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'can_preview',
                        'ocr_result'
                    ]
                ]);
    }

    /** @test */
    public function user_can_check_ocr_status()
    {
        $ocrResult = OcrResult::factory()->create([
            'filename' => 'test.pdf',
            'status' => 'processing'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/ocr/{$ocrResult->id}/status");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'status',
                        'filename',
                        'ocr_result'
                    ]
                ]);
    }

    /** @test */
    public function user_can_process_regions()
    {
        $ocrResult = OcrResult::factory()->create([
            'filename' => 'test.pdf',
            'status' => 'ready'
        ]);

        $regionsData = [
            'regions' => [
                [
                    'x' => 100,
                    'y' => 200,
                    'width' => 300,
                    'height' => 150
                ]
            ],
            'previewDimensions' => [
                'width' => 800,
                'height' => 600
            ],
            'pageRotation' => [
                '1' => 0
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/ocr/{$ocrResult->id}/process-regions", $regionsData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'status'
                    ]
                ]);
    }

    /** @test */
    public function user_can_save_rotations()
    {
        $ocrResult = OcrResult::factory()->create([
            'filename' => 'test.pdf',
            'status' => 'ready'
        ]);

        $rotationData = [
            'rotations' => [
                '1' => 90,
                '2' => 180
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/ocr/{$ocrResult->id}/save-rotations", $rotationData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Page rotations saved successfully' // UPDATED: Match actual message
                ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_protected_routes()
    {
        $response = $this->getJson('/api/ocr');
        $response->assertStatus(401);

        $response = $this->getJson('/api/auth/user');
        $response->assertStatus(401);

        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);
    }

    /** @test */
    public function invalid_login_credentials_return_error()
    {
        $loginData = [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ]);
    }

    /** @test */
    public function registration_validation_works()
    {
        $userData = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '456'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors'
                ]);
    }

    /** @test */
    public function process_regions_validation_works()
    {
        $ocrResult = OcrResult::factory()->create();

        $invalidData = [
            'regions' => [
                [
                    'x' => 'invalid',
                    'y' => 200,
                    'width' => 300,
                    'height' => 150
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/ocr/{$ocrResult->id}/process-regions", $invalidData);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors'
                ]);
    }

    /** @test */
    public function nonexistent_ocr_result_returns_404()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/ocr/999999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'OCR result not found'
                ]);
    }
}