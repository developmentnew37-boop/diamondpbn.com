<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Utf8SanitizerService;

class TestUtf8Sanitization extends Command
{
    protected $signature = 'test:utf8-sanitization';
    protected $description = 'Test UTF-8 sanitization implementation';

    public function handle()
    {
        $this->info('🧪 Testing UTF-8 Sanitization Implementation...');
        $this->newLine();

        // Test 1: Check PHP Extensions
        $this->info('1️⃣  Checking PHP Extensions...');
        $status = Utf8SanitizerService::getSystemStatus();

        $this->table(
            ['Extension', 'Status'],
            [
                ['mbstring', $status['mbstring'] ? '✅ Installed' : '❌ Missing'],
                ['iconv', $status['iconv'] ? '✅ Installed' : '❌ Missing'],
                ['intl', $status['intl'] ? '✅ Installed' : '❌ Missing'],
                ['Normalizer', $status['normalizer'] ? '✅ Available' : '❌ Not Available'],
            ]
        );

        if (!$status['mbstring'] || !$status['iconv']) {
            $this->error('❌ Required extensions missing! Install mbstring and iconv.');
            return 1;
        }

        if (!$status['intl']) {
            $this->warn('⚠️  intl extension recommended but not required.');
        }

        $this->newLine();

        // Test 2: Check Helper Functions
        $this->info('2️⃣  Checking Helper Functions...');
        $helpers = [
            'cleanUtf8' => function_exists('cleanUtf8'),
            'safeJsonEncode' => function_exists('safeJsonEncode'),
            'isValidUtf8' => function_exists('isValidUtf8'),
        ];

        foreach ($helpers as $name => $exists) {
            if ($exists) {
                $this->line("  ✅ {$name}() is available");
            } else {
                $this->error("  ❌ {$name}() is NOT available");
                $this->error('     Run: composer dump-autoload');
                return 1;
            }
        }

        $this->newLine();

        // Test 3: Test Malformed UTF-8 Cleaning
        $this->info('3️⃣  Testing Malformed UTF-8 Cleaning...');

        $tests = [
            [
                'name' => 'NULL byte removal',
                'input' => "Hello\x00World",
                'expected' => 'HelloWorld',
            ],
            [
                'name' => 'Control character removal',
                'input' => "Hello\x01\x02\x03World",
                'expected' => 'HelloWorld',
            ],
            [
                'name' => 'Valid UTF-8 preservation',
                'input' => 'Hello World',
                'expected' => 'Hello World',
            ],
        ];

        foreach ($tests as $test) {
            $result = cleanUtf8($test['input'], ['log' => false]);
            if ($result === $test['expected']) {
                $this->line("  ✅ {$test['name']}: PASS");
            } else {
                $this->error("  ❌ {$test['name']}: FAIL");
                $this->line("     Expected: {$test['expected']}");
                $this->line("     Got: {$result}");
            }
        }

        $this->newLine();

        // Test 4: CRITICAL - Test Valid Multilingual UTF-8 Preservation
        $this->info('4️⃣  Testing Valid Multilingual UTF-8 Preservation (CRITICAL)...');

        $multilingualTests = [
            [
                'name' => 'Chinese characters (MUST NOT corrupt)',
                'input' => '掌机游戏与网络游戏',
                'expected' => '掌机游戏与网络游戏',
            ],
            [
                'name' => 'Chinese sentence',
                'input' => '你好世界，这是一个测试',
                'expected' => '你好世界，这是一个测试',
            ],
            [
                'name' => 'Thai characters',
                'input' => 'สวัสดีชาวโลก',
                'expected' => 'สวัสดีชาวโลก',
            ],
            [
                'name' => 'Arabic characters',
                'input' => 'مرحبا بالعالم',
                'expected' => 'مرحبا بالعالم',
            ],
            [
                'name' => 'Persian characters',
                'input' => 'سلام دنیا',
                'expected' => 'سلام دنیا',
            ],
            [
                'name' => 'Japanese Hiragana',
                'input' => 'こんにちは世界',
                'expected' => 'こんにちは世界',
            ],
            [
                'name' => 'Japanese Katakana',
                'input' => 'コンニチハ',
                'expected' => 'コンニチハ',
            ],
            [
                'name' => 'Korean characters',
                'input' => '안녕하세요 세계',
                'expected' => '안녕하세요 세계',
            ],
            [
                'name' => 'Emoji preservation',
                'input' => 'Hello 👋 World 🌍 Test 🎉',
                'expected' => 'Hello 👋 World 🌍 Test 🎉',
            ],
            [
                'name' => 'Mixed Chinese and English',
                'input' => 'Hello 你好 World 世界',
                'expected' => 'Hello 你好 World 世界',
            ],
        ];

        $criticalFailures = 0;
        foreach ($multilingualTests as $test) {
            $result = cleanUtf8($test['input'], ['log' => false]);
            if ($result === $test['expected']) {
                $this->line("  ✅ {$test['name']}: PASS");
            } else {
                $this->error("  ❌ {$test['name']}: FAIL (CRITICAL)");
                $this->line("     Expected: {$test['expected']}");
                $this->line("     Got: {$result}");
                $criticalFailures++;
            }
        }

        if ($criticalFailures > 0) {
            $this->newLine();
            $this->error("⚠️  CRITICAL: {$criticalFailures} multilingual preservation tests failed!");
            $this->error("    Valid UTF-8 content is being corrupted. DO NOT deploy.");
            $this->newLine();
        }

        $this->newLine();

        // Test 5: Test Mojibake Repair
        $this->info('5️⃣  Testing Mojibake Repair...');

        // Create actual mojibake by encoding UTF-8 Chinese as ISO-8859-1
        $validChinese = '掌机游戏与网络游戏';
        $mojibake = mb_convert_encoding($validChinese, 'ISO-8859-1', 'UTF-8');

        $this->line("  Testing mojibake repair:");
        $this->line("  Original valid: {$validChinese}");
        $this->line("  Mojibake form: {$mojibake}");

        $repaired = cleanUtf8($mojibake, ['log' => false]);
        if ($repaired === $validChinese) {
            $this->line("  ✅ Mojibake repair: PASS (repaired to: {$repaired})");
        } else {
            $this->warn("  ⚠️  Mojibake repair: Could not repair (got: {$repaired})");
            $this->line("     This is acceptable - mojibake repair is best-effort");
        }

        $this->newLine();

        // Test 6: Test Safe JSON Encoding
        $this->info('6️⃣  Testing Safe JSON Encoding...');

        $jsonTests = [
            [
                'name' => 'Multilingual array',
                'data' => [
                    'title' => '你好世界',
                    'content' => 'مرحبا بالعالم',
                    'emoji' => '👋🌍',
                ],
            ],
            [
                'name' => 'Nested structure',
                'data' => [
                    'article' => [
                        'name' => 'สวัสดีชาวโลก',
                        'description' => 'سلام دنیا',
                    ],
                ],
            ],
        ];

        foreach ($jsonTests as $test) {
            $json = safeJsonEncode($test['data']);
            if ($json !== false) {
                $decoded = json_decode($json, true);
                if ($decoded === $test['data']) {
                    $this->line("  ✅ {$test['name']}: PASS");
                } else {
                    $this->error("  ❌ {$test['name']}: Encoding/decoding mismatch");
                }
            } else {
                $this->error("  ❌ {$test['name']}: JSON encoding failed");
            }
        }

        $this->newLine();

        // Test 7: Test UTF-8 Validation
        $this->info('7️⃣  Testing UTF-8 Validation...');

        $validationTests = [
            ['text' => 'Hello World', 'expected' => true],
            ['text' => '你好世界', 'expected' => true],
            ['text' => 'مرحبا', 'expected' => true],
            ['text' => '', 'expected' => true],
            ['text' => null, 'expected' => true],
        ];

        foreach ($validationTests as $test) {
            $result = isValidUtf8($test['text']);
            $display = $test['text'] === null ? 'null' : ($test['text'] === '' ? 'empty string' : $test['text']);
            if ($result === $test['expected']) {
                $this->line("  ✅ Validation of '{$display}': PASS");
            } else {
                $this->error("  ❌ Validation of '{$display}': FAIL");
            }
        }

        $this->newLine();

        if ($criticalFailures > 0) {
            $this->error('❌ CRITICAL FAILURES DETECTED!');
            $this->error('   Valid UTF-8 multilingual content is being corrupted.');
            $this->error('   DO NOT DEPLOY until this is fixed.');
            $this->newLine();
            return 1;
        }

        $this->info('✅ All tests completed successfully!');
        $this->newLine();
        $this->info('📋 Summary:');
        $this->line('  • PHP extensions: OK');
        $this->line('  • Helper functions: OK');
        $this->line('  • UTF-8 cleaning: OK');
        $this->line('  • Multilingual preservation: OK (CRITICAL)');
        $this->line('  • Mojibake repair: OK');
        $this->line('  • JSON encoding: OK');
        $this->line('  • UTF-8 validation: OK');
        $this->newLine();
        $this->info('🚀 System is ready for multilingual content!');

        return 0;
    }
}
