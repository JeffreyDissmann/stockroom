import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Items\RelatedItemController::store
* @see app/Http/Controllers/Items/RelatedItemController.php:20
* @route '/items/{item}/related-items'
*/
export const store = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/items/{item}/related-items',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Items\RelatedItemController::store
* @see app/Http/Controllers/Items/RelatedItemController.php:20
* @route '/items/{item}/related-items'
*/
store.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { item: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { item: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            item: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
    }

    return store.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\RelatedItemController::store
* @see app/Http/Controllers/Items/RelatedItemController.php:20
* @route '/items/{item}/related-items'
*/
store.post = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Items\RelatedItemController::destroy
* @see app/Http/Controllers/Items/RelatedItemController.php:27
* @route '/items/{item}/related-items/{related}'
*/
export const destroy = (args: { item: number | { id: number }, related: number | { id: number } } | [item: number | { id: number }, related: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/items/{item}/related-items/{related}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Items\RelatedItemController::destroy
* @see app/Http/Controllers/Items/RelatedItemController.php:27
* @route '/items/{item}/related-items/{related}'
*/
destroy.url = (args: { item: number | { id: number }, related: number | { id: number } } | [item: number | { id: number }, related: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            item: args[0],
            related: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
        related: typeof args.related === 'object'
        ? args.related.id
        : args.related,
    }

    return destroy.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace('{related}', parsedArgs.related.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\RelatedItemController::destroy
* @see app/Http/Controllers/Items/RelatedItemController.php:27
* @route '/items/{item}/related-items/{related}'
*/
destroy.delete = (args: { item: number | { id: number }, related: number | { id: number } } | [item: number | { id: number }, related: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const RelatedItemController = { store, destroy }

export default RelatedItemController