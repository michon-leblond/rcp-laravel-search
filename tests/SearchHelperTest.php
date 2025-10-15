<?php

namespace Rcp\LaravelSearch\Tests;

use Rcp\LaravelSearch\Tests\TestCase;
use Rcp\LaravelSearch\Helpers\SearchHelper;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class SearchHelperTest extends TestCase
{
    /** @test */
    public function it_can_apply_text_filters()
    {
        $request = new Request(['name' => 'test']);
        
        // Mock query builder
        $query = $this->createMock(Builder::class);
        $query->expects($this->once())
              ->method('where')
              ->with('name', 'LIKE', '%test%');
        
        $filters = ['name' => 'text'];
        
        SearchHelper::apply($query, $request, $filters);
    }

    /** @test */
    public function it_can_apply_exact_filters()
    {
        $request = new Request(['status' => 'active']);
        
        $query = $this->createMock(Builder::class);
        $query->expects($this->once())
              ->method('where')
              ->with('status', 'active');
        
        $filters = ['status' => 'status'];
        
        SearchHelper::apply($query, $request, $filters);
    }
}