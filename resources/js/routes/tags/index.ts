import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\TagController::index
* @see app/Http/Controllers/TagController.php:18
* @route '/tags'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/tags',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TagController::index
* @see app/Http/Controllers/TagController.php:18
* @route '/tags'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TagController::index
* @see app/Http/Controllers/TagController.php:18
* @route '/tags'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TagController::index
* @see app/Http/Controllers/TagController.php:18
* @route '/tags'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TagController::store
* @see app/Http/Controllers/TagController.php:38
* @route '/tags'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/tags',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\TagController::store
* @see app/Http/Controllers/TagController.php:38
* @route '/tags'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TagController::store
* @see app/Http/Controllers/TagController.php:38
* @route '/tags'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\TagController::update
* @see app/Http/Controllers/TagController.php:45
* @route '/tags/{tag}'
*/
export const update = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/tags/{tag}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\TagController::update
* @see app/Http/Controllers/TagController.php:45
* @route '/tags/{tag}'
*/
update.url = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tag: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { tag: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            tag: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tag: typeof args.tag === 'object'
        ? args.tag.id
        : args.tag,
    }

    return update.definition.url
            .replace('{tag}', parsedArgs.tag.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TagController::update
* @see app/Http/Controllers/TagController.php:45
* @route '/tags/{tag}'
*/
update.put = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\TagController::update
* @see app/Http/Controllers/TagController.php:45
* @route '/tags/{tag}'
*/
update.patch = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\TagController::destroy
* @see app/Http/Controllers/TagController.php:52
* @route '/tags/{tag}'
*/
export const destroy = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/tags/{tag}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\TagController::destroy
* @see app/Http/Controllers/TagController.php:52
* @route '/tags/{tag}'
*/
destroy.url = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tag: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { tag: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            tag: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tag: typeof args.tag === 'object'
        ? args.tag.id
        : args.tag,
    }

    return destroy.definition.url
            .replace('{tag}', parsedArgs.tag.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TagController::destroy
* @see app/Http/Controllers/TagController.php:52
* @route '/tags/{tag}'
*/
destroy.delete = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const tags = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default tags