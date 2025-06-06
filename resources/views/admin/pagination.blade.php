@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-neutral-500 bg-white border border-neutral-300 cursor-default rounded-md leading-5">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded-md leading-5 hover:text-neutral-500 focus:outline-none focus:ring ring-neutral-300 focus:border-primary-300 active:bg-neutral-100 active:text-neutral-700 transition ease-in-out duration-150">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded-md leading-5 hover:text-neutral-500 focus:outline-none focus:ring ring-neutral-300 focus:border-primary-300 active:bg-neutral-100 active:text-neutral-700 transition ease-in-out duration-150">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-neutral-500 bg-white border border-neutral-300 cursor-default rounded-md leading-5">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-{{ $alignment ?? 'between' }}">
            <div>
                <p class="text-sm text-neutral-700 leading-5">
                    {!! __('Showing') !!}
                    <span class="font-medium">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-medium">{{ $paginator->lastItem() }}</span>
                    {!! __('of') !!}
                    <span class="font-medium">{{ $paginator->total() }}</span>
                    {!! __('results') !!}
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex shadow-sm rounded-md">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-neutral-500 bg-white border border-neutral-300 cursor-default rounded-l-md leading-5" aria-hidden="true">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-neutral-500 bg-white border border-neutral-300 rounded-l-md leading-5 hover:text-neutral-400 focus:z-10 focus:outline-none focus:ring ring-neutral-300 focus:border-primary-300 active:bg-neutral-100 active:text-neutral-500 transition ease-in-out duration-150" aria-label="{{ __('pagination.previous') }}">
                            <i class="fa-solid fa-chevron-left text-xs"></i>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-neutral-700 bg-white border border-neutral-300 cursor-default leading-5">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-primary-600 bg-primary-50 border border-neutral-300 cursor-default leading-5">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-neutral-700 bg-white border border-neutral-300 leading-5 hover:bg-neutral-50 focus:z-10 focus:outline-none focus:ring ring-neutral-300 focus:border-primary-300 active:bg-neutral-100 active:text-neutral-700 transition ease-in-out duration-150" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-neutral-500 bg-white border border-neutral-300 rounded-r-md leading-5 hover:text-neutral-400 focus:z-10 focus:outline-none focus:ring ring-neutral-300 focus:border-primary-300 active:bg-neutral-100 active:text-neutral-500 transition ease-in-out duration-150" aria-label="{{ __('pagination.next') }}">
                            <i class="fa-solid fa-chevron-right text-xs"></i>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-neutral-500 bg-white border border-neutral-300 cursor-default rounded-r-md leading-5" aria-hidden="true">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif