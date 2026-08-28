<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ServiceNormalizerTest extends TestCase
{
    private ServiceNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ServiceNormalizer();
    }

    public function testNormalizeArrayOfServices(): void
    {
        $result = $this->normalizer->normalize([
            ['title' => 'Консультация', 'price' => 1500, 'quantity' => 2, 'id' => 42],
            ['name' => 'УЗИ', 'cost' => 3000],
        ], null);

        $this->assertCount(2, $result);
        $this->assertSame('Консультация', $result[0]['name']);
        $this->assertSame(1500.0, $result[0]['price']);
        $this->assertSame(2.0, $result[0]['qty']);
        $this->assertSame('rnova_svc_42', $result[0]['xml_id']);
        $this->assertSame('УЗИ', $result[1]['name']);
        $this->assertSame(3000.0, $result[1]['price']);
    }

    public function testNormalizeLegacyStringWithAmount(): void
    {
        $result = $this->normalizer->normalize('Консультация', 2000);
        $this->assertCount(1, $result);
        $this->assertSame('Консультация', $result[0]['name']);
        $this->assertSame(2000.0, $result[0]['price']);
    }

    public function testNormalizeJsonString(): void
    {
        $json = json_encode([['title' => 'A', 'price' => 100]]);
        $result = $this->normalizer->normalize($json, null);
        $this->assertCount(1, $result);
        $this->assertSame('A', $result[0]['name']);
    }

    public function testFormatForDealFieldAndTotal(): void
    {
        $lines = [
            ['name' => 'A', 'price' => 100.0, 'qty' => 2.0, 'xml_id' => null],
            ['name' => 'B', 'price' => 50.0, 'qty' => 1.0, 'xml_id' => null],
        ];
        $this->assertSame("A: 200\nB: 50", $this->normalizer->formatForDealField($lines));
        $this->assertSame(250.0, $this->normalizer->total($lines));
    }

    public function testEmptyInput(): void
    {
        $this->assertSame([], $this->normalizer->normalize(null, null));
        $this->assertSame([], $this->normalizer->normalize([], null));
    }
}
