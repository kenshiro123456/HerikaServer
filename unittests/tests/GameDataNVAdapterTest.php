<?php declare(strict_types=1);

require_once 'DatabaseTestCase.php';

/**
 * Property-Based Tests for New Vegas Adapter Translation Correctness
 * Feature: nv-herika-transmission
 * Property 8: Adapter Translation Correctness
 * Validates: Requirements 4.2, 4.3, 4.4, 5.1, 5.2, 5.3, 5.4
 */
final class GameDataNVAdapterTest extends DatabaseTestCase
{
    private const ITERATIONS = 100;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Load the adapter functions
        require_once(__DIR__ . '/../../gamedata_nv.php');
    }
    
    /**
     * Property Test: Subtitle translation should correctly map all fields
     * For any valid subtitle event, all fields should be correctly translated
     * to their Skyrim equivalents according to the translation mapping.
     */
    public function testProperty_SubtitleTranslation_MapsAllFieldsCorrectly(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $nvData = $this->generateRandomSubtitleEvent();
            
            $skyrimData = translateSubtitle($nvData);
            
            // Verify field mappings according to translation table
            $this->assertEquals($nvData['speaker_name'], $skyrimData['actor_name'], 
                "speaker_name should map to actor_name");
            $this->assertEquals($nvData['text'], $skyrimData['dialogue_text'], 
                "text should map to dialogue_text");
            
            // Verify optional fields are preserved
            if (isset($nvData['speaker_refid'])) {
                $this->assertEquals($nvData['speaker_refid'], $skyrimData['refid'], 
                    "speaker_refid should map to refid");
            }
            if (isset($nvData['speaker_baseid'])) {
                $this->assertEquals($nvData['speaker_baseid'], $skyrimData['baseid'], 
                    "speaker_baseid should map to baseid");
            }
            if (isset($nvData['cell'])) {
                $this->assertEquals($nvData['cell'], $skyrimData['location'], 
                    "cell should map to location");
            }
            
            // Verify timestamp conversion
            $this->assertIsInt($skyrimData['ts'], "ts should be Unix timestamp");
            $this->assertEquals($nvData['timestamp'], $skyrimData['game_ts'], 
                "game_ts should preserve original timestamp");
            
            // Verify metadata preservation
            $this->assertArrayHasKey('nv_metadata', $skyrimData, 
                "Should have nv_metadata section");
            if (isset($nvData['cellFormID'])) {
                $this->assertEquals($nvData['cellFormID'], $skyrimData['nv_metadata']['cellFormID'], 
                    "cellFormID should be preserved in nv_metadata");
            }
            if (isset($nvData['topicinfo_id'])) {
                $this->assertEquals($nvData['topicinfo_id'], $skyrimData['nv_metadata']['topicinfo_id'], 
                    "topicinfo_id should be preserved in nv_metadata");
            }
        }
    }
    
    /**
     * Property Test: Location translation should correctly map all fields
     * For any valid location event, all fields should be correctly translated
     * to their Skyrim equivalents.
     */
    public function testProperty_LocationTranslation_MapsAllFieldsCorrectly(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $nvData = $this->generateRandomLocationEvent();
            
            $skyrimData = translateLocation($nvData);
            
            // Verify field mappings
            $this->assertEquals('Player', $skyrimData['actor_name'], 
                "actor_name should always be Player for location events");
            $this->assertEquals($nvData['cell'], $skyrimData['cell'], 
                "cell should be preserved");
            
            // Location field should use location if present, otherwise cell
            if (isset($nvData['location'])) {
                $this->assertEquals($nvData['location'], $skyrimData['location'], 
                    "location should be preserved when present");
            } else {
                $this->assertEquals($nvData['cell'], $skyrimData['location'], 
                    "location should default to cell when not present");
            }
            
            // Verify worldspace preservation
            if (isset($nvData['worldspace'])) {
                $this->assertEquals($nvData['worldspace'], $skyrimData['worldspace'], 
                    "worldspace should be preserved");
            }
            
            // Verify position coordinates
            if (isset($nvData['pos'])) {
                $this->assertEquals(floatval($nvData['pos']['x']), $skyrimData['position_x'], 
                    "position_x should be preserved as float");
                $this->assertEquals(floatval($nvData['pos']['y']), $skyrimData['position_y'], 
                    "position_y should be preserved as float");
                $this->assertEquals(floatval($nvData['pos']['z']), $skyrimData['position_z'], 
                    "position_z should be preserved as float");
            }
            
            // Verify interior flag
            if (isset($nvData['interior'])) {
                $this->assertEquals(boolval($nvData['interior']), $skyrimData['interior'], 
                    "interior flag should be preserved as boolean");
            }
            
            // Verify timestamp conversion
            $this->assertIsInt($skyrimData['ts'], "ts should be Unix timestamp");
            $this->assertEquals($nvData['timestamp'], $skyrimData['game_ts'], 
                "game_ts should preserve original timestamp");
            
            // Verify metadata preservation
            $this->assertArrayHasKey('nv_metadata', $skyrimData, 
                "Should have nv_metadata section");
            if (isset($nvData['cellFormID'])) {
                $this->assertEquals($nvData['cellFormID'], $skyrimData['nv_metadata']['cellFormID'], 
                    "cellFormID should be preserved in nv_metadata");
            }
            if (isset($nvData['worldspaceFormID'])) {
                $this->assertEquals($nvData['worldspaceFormID'], $skyrimData['nv_metadata']['worldspaceFormID'], 
                    "worldspaceFormID should be preserved in nv_metadata");
            }
        }
    }
    
    /**
     * Property Test: User input translation should correctly map all fields
     * For any valid user input event, all fields should be correctly translated
     * to their Skyrim equivalents.
     */
    public function testProperty_UserInputTranslation_MapsAllFieldsCorrectly(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $nvData = $this->generateRandomUserInputEvent();
            
            $skyrimData = translateUserInput($nvData);
            
            // Verify field mappings
            $this->assertEquals('Player', $skyrimData['actor_name'], 
                "actor_name should always be Player for user input events");
            $this->assertEquals($nvData['input'], $skyrimData['input_text'], 
                "input should map to input_text");
            
            // Verify timestamp conversion
            $this->assertIsInt($skyrimData['ts'], "ts should be Unix timestamp");
            $this->assertEquals($nvData['timestamp'], $skyrimData['game_ts'], 
                "game_ts should preserve original timestamp");
            
            // Verify location data preservation
            if (isset($nvData['location'])) {
                $this->assertEquals($nvData['location'], $skyrimData['location'], 
                    "location should be preserved");
                $this->assertEquals($nvData['location'], $skyrimData['nv_metadata']['location'], 
                    "location should be preserved in nv_metadata");
            }
            
            // Verify target data preservation
            if (isset($nvData['target'])) {
                $this->assertEquals($nvData['target'], $skyrimData['target'], 
                    "target should be preserved");
                $this->assertEquals($nvData['target'], $skyrimData['nv_metadata']['target'], 
                    "target should be preserved in nv_metadata");
            }
            
            // Verify metadata section exists
            $this->assertArrayHasKey('nv_metadata', $skyrimData, 
                "Should have nv_metadata section");
        }
    }
    
    /**
     * Property Test: FormIDs should be preserved in hexadecimal format
     * For any event with FormIDs, they should be preserved exactly as provided
     * (in hexadecimal format).
     */
    public function testProperty_FormIDs_PreservedInHexFormat(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $hexFormID = $this->generateRandomHexFormID();
            
            // Test with subtitle event
            $nvData = $this->generateRandomSubtitleEvent();
            $nvData['speaker_refid'] = $hexFormID;
            $nvData['speaker_baseid'] = $hexFormID;
            
            $skyrimData = translateSubtitle($nvData);
            
            $this->assertEquals($hexFormID, $skyrimData['refid'], 
                "speaker_refid should be preserved in hex format");
            $this->assertEquals($hexFormID, $skyrimData['baseid'], 
                "speaker_baseid should be preserved in hex format");
            
            // Test with location event
            $nvData = $this->generateRandomLocationEvent();
            $nvData['cellFormID'] = $hexFormID;
            $nvData['worldspaceFormID'] = $hexFormID;
            
            $skyrimData = translateLocation($nvData);
            
            $this->assertEquals($hexFormID, $skyrimData['nv_metadata']['cellFormID'], 
                "cellFormID should be preserved in hex format in nv_metadata");
            $this->assertEquals($hexFormID, $skyrimData['nv_metadata']['worldspaceFormID'], 
                "worldspaceFormID should be preserved in hex format in nv_metadata");
        }
    }
    
    /**
     * Property Test: All NV-specific fields should be preserved in nv_metadata
     * For any event, all New Vegas-specific fields should be preserved in the
     * nv_metadata section for future use.
     */
    public function testProperty_NVMetadata_PreservesAllNVSpecificFields(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Test subtitle event
            $nvData = $this->generateRandomSubtitleEvent();
            $skyrimData = translateSubtitle($nvData);
            
            $this->assertArrayHasKey('nv_metadata', $skyrimData, 
                "Subtitle event should have nv_metadata");
            
            // Test location event
            $nvData = $this->generateRandomLocationEvent();
            $skyrimData = translateLocation($nvData);
            
            $this->assertArrayHasKey('nv_metadata', $skyrimData, 
                "Location event should have nv_metadata");
            
            // Test user input event
            $nvData = $this->generateRandomUserInputEvent();
            $skyrimData = translateUserInput($nvData);
            
            $this->assertArrayHasKey('nv_metadata', $skyrimData, 
                "User input event should have nv_metadata");
        }
    }
    
    // ========== Generator Functions ==========
    
    private function generateRandomSubtitleEvent(): array
    {
        return [
            'type' => 'subtitle',
            'speaker_name' => $this->generateRandomName(),
            'speaker_refid' => $this->generateRandomHexFormID(),
            'speaker_baseid' => $this->generateRandomHexFormID(),
            'text' => $this->generateRandomDialogue(),
            'cell' => $this->generateRandomLocation(),
            'cellFormID' => $this->generateRandomHexFormID(),
            'topicinfo_id' => $this->generateRandomHexFormID(),
            'timestamp' => $this->generateRandomTimestamp()
        ];
    }
    
    private function generateRandomLocationEvent(): array
    {
        $event = [
            'type' => 'location',
            'cell' => $this->generateRandomLocation(),
            'timestamp' => $this->generateRandomTimestamp()
        ];
        
        // Add optional fields randomly
        if (rand(0, 1)) {
            $event['location'] = $this->generateRandomLocation();
        }
        if (rand(0, 1)) {
            $event['worldspace'] = $this->generateRandomWorldspace();
        }
        if (rand(0, 1)) {
            $event['pos'] = [
                'x' => $this->generateRandomCoordinate(),
                'y' => $this->generateRandomCoordinate(),
                'z' => $this->generateRandomCoordinate()
            ];
        }
        if (rand(0, 1)) {
            $event['interior'] = (bool)rand(0, 1);
        }
        if (rand(0, 1)) {
            $event['cellFormID'] = $this->generateRandomHexFormID();
        }
        if (rand(0, 1)) {
            $event['worldspaceFormID'] = $this->generateRandomHexFormID();
        }
        
        return $event;
    }
    
    private function generateRandomUserInputEvent(): array
    {
        $event = [
            'type' => 'user_input',
            'input' => $this->generateRandomUserInput(),
            'timestamp' => $this->generateRandomTimestamp()
        ];
        
        // Add optional location data randomly
        if (rand(0, 1)) {
            $event['location'] = [
                'cell' => $this->generateRandomLocation(),
                'worldspace' => $this->generateRandomWorldspace(),
                'pos' => [
                    'x' => $this->generateRandomCoordinate(),
                    'y' => $this->generateRandomCoordinate(),
                    'z' => $this->generateRandomCoordinate()
                ],
                'interior' => (bool)rand(0, 1),
                'cellFormID' => $this->generateRandomHexFormID(),
                'worldspaceFormID' => $this->generateRandomHexFormID()
            ];
        }
        
        // Add optional target data randomly
        if (rand(0, 1)) {
            $event['target'] = [
                'name' => $this->generateRandomName(),
                'refID' => $this->generateRandomHexFormID(),
                'baseFormID' => $this->generateRandomHexFormID(),
                'cell' => $this->generateRandomLocation(),
                'cellFormID' => $this->generateRandomHexFormID()
            ];
        }
        
        return $event;
    }
    
    private function generateRandomName(): string
    {
        $names = [
            'Benny', 'Mr. House', 'Caesar', 'Boone', 'Veronica', 'Arcade',
            'Cass', 'Raul', 'Lily', 'Rex', 'ED-E', 'Yes Man', 'Victor',
            'Doc Mitchell', 'Sunny Smiles', 'Trudy', 'Easy Pete', 'Ringo'
        ];
        return $names[array_rand($names)];
    }
    
    private function generateRandomDialogue(): string
    {
        $dialogues = [
            "What in the goddamn...?",
            "The game was rigged from the start.",
            "Patrolling the Mojave almost makes you wish for a nuclear winter.",
            "Ave, true to Caesar.",
            "NCR won't go quietly, the Legion can count on that.",
            "War never changes.",
            "Truth is, the game was rigged from the start.",
            "They asked me how well I understood theoretical physics.",
            "I survived because the fire inside burned brighter than the fire around me."
        ];
        return $dialogues[array_rand($dialogues)];
    }
    
    private function generateRandomLocation(): string
    {
        $locations = [
            'Goodsprings', 'Primm', 'Novac', 'Nipton', 'Boulder City',
            'Freeside', 'New Vegas Strip', 'The Tops Casino', 'Ultra-Luxe',
            'Gomorrah', 'Camp McCarran', 'Hoover Dam', 'Jacobstown',
            'Red Rock Canyon', 'Hidden Valley', 'Black Mountain'
        ];
        return $locations[array_rand($locations)];
    }
    
    private function generateRandomWorldspace(): string
    {
        $worldspaces = [
            'Mojave Wasteland', 'New Vegas', 'Freeside', 'The Strip',
            'Hoover Dam', 'Camp McCarran', 'Nellis AFB'
        ];
        return $worldspaces[array_rand($worldspaces)];
    }
    
    private function generateRandomUserInput(): string
    {
        $inputs = [
            "Tell me about the platinum chip",
            "What do you know about Mr. House?",
            "Where can I find the Brotherhood of Steel?",
            "I need information about Caesar's Legion",
            "What's the situation with the NCR?",
            "Can you help me with this quest?",
            "Tell me about yourself"
        ];
        return $inputs[array_rand($inputs)];
    }
    
    private function generateRandomHexFormID(): string
    {
        return sprintf('%08X', rand(0, 0xFFFFFFFF));
    }
    
    private function generateRandomCoordinate(): float
    {
        return round((rand(-10000, 10000) + (rand(0, 99) / 100)), 2);
    }
    
    private function generateRandomTimestamp(): string
    {
        $year = rand(2024, 2026);
        $month = str_pad((string)rand(1, 12), 2, '0', STR_PAD_LEFT);
        $day = str_pad((string)rand(1, 28), 2, '0', STR_PAD_LEFT);
        $hour = str_pad((string)rand(0, 23), 2, '0', STR_PAD_LEFT);
        $minute = str_pad((string)rand(0, 59), 2, '0', STR_PAD_LEFT);
        $second = str_pad((string)rand(0, 59), 2, '0', STR_PAD_LEFT);
        $millisecond = str_pad((string)rand(0, 999), 3, '0', STR_PAD_LEFT);
        
        return "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}.{$millisecond}";
    }
}
