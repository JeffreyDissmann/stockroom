import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Items\HomeAssistantLinkController::destroy
* @see app/Http/Controllers/Items/HomeAssistantLinkController.php:19
* @route '/items/{item}/home-assistant-link'
*/
export const destroy = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/items/{item}/home-assistant-link',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Items\HomeAssistantLinkController::destroy
* @see app/Http/Controllers/Items/HomeAssistantLinkController.php:19
* @route '/items/{item}/home-assistant-link'
*/
destroy.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\HomeAssistantLinkController::destroy
* @see app/Http/Controllers/Items/HomeAssistantLinkController.php:19
* @route '/items/{item}/home-assistant-link'
*/
destroy.delete = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const homeAssistantLink = {
    destroy: Object.assign(destroy, destroy),
}

export default homeAssistantLink