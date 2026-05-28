import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\CustomFieldController::index
* @see app/Http/Controllers/Household/CustomFieldController.php:18
* @route '/household/custom-fields'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/household/custom-fields',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Household\CustomFieldController::index
* @see app/Http/Controllers/Household/CustomFieldController.php:18
* @route '/household/custom-fields'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\CustomFieldController::index
* @see app/Http/Controllers/Household/CustomFieldController.php:18
* @route '/household/custom-fields'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Household\CustomFieldController::index
* @see app/Http/Controllers/Household/CustomFieldController.php:18
* @route '/household/custom-fields'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Household\CustomFieldController::store
* @see app/Http/Controllers/Household/CustomFieldController.php:30
* @route '/household/custom-fields'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/household/custom-fields',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\CustomFieldController::store
* @see app/Http/Controllers/Household/CustomFieldController.php:30
* @route '/household/custom-fields'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\CustomFieldController::store
* @see app/Http/Controllers/Household/CustomFieldController.php:30
* @route '/household/custom-fields'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Household\CustomFieldController::update
* @see app/Http/Controllers/Household/CustomFieldController.php:42
* @route '/household/custom-fields/{customField}'
*/
export const update = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/household/custom-fields/{customField}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Household\CustomFieldController::update
* @see app/Http/Controllers/Household/CustomFieldController.php:42
* @route '/household/custom-fields/{customField}'
*/
update.url = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { customField: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { customField: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            customField: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        customField: typeof args.customField === 'object'
        ? args.customField.id
        : args.customField,
    }

    return update.definition.url
            .replace('{customField}', parsedArgs.customField.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\CustomFieldController::update
* @see app/Http/Controllers/Household/CustomFieldController.php:42
* @route '/household/custom-fields/{customField}'
*/
update.put = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Household\CustomFieldController::destroy
* @see app/Http/Controllers/Household/CustomFieldController.php:62
* @route '/household/custom-fields/{customField}'
*/
export const destroy = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/household/custom-fields/{customField}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Household\CustomFieldController::destroy
* @see app/Http/Controllers/Household/CustomFieldController.php:62
* @route '/household/custom-fields/{customField}'
*/
destroy.url = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { customField: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { customField: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            customField: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        customField: typeof args.customField === 'object'
        ? args.customField.id
        : args.customField,
    }

    return destroy.definition.url
            .replace('{customField}', parsedArgs.customField.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\CustomFieldController::destroy
* @see app/Http/Controllers/Household/CustomFieldController.php:62
* @route '/household/custom-fields/{customField}'
*/
destroy.delete = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const CustomFieldController = { index, store, update, destroy }

export default CustomFieldController