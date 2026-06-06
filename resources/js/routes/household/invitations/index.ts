import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\InvitationController::store
* @see app/Http/Controllers/Household/InvitationController.php:45
* @route '/household/invitations'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/household/invitations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\InvitationController::store
* @see app/Http/Controllers/Household/InvitationController.php:45
* @route '/household/invitations'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\InvitationController::store
* @see app/Http/Controllers/Household/InvitationController.php:45
* @route '/household/invitations'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Household\InvitationController::destroy
* @see app/Http/Controllers/Household/InvitationController.php:94
* @route '/household/invitations/{invitation}'
*/
export const destroy = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/household/invitations/{invitation}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Household\InvitationController::destroy
* @see app/Http/Controllers/Household/InvitationController.php:94
* @route '/household/invitations/{invitation}'
*/
destroy.url = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { invitation: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { invitation: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            invitation: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        invitation: typeof args.invitation === 'object'
        ? args.invitation.id
        : args.invitation,
    }

    return destroy.definition.url
            .replace('{invitation}', parsedArgs.invitation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\InvitationController::destroy
* @see app/Http/Controllers/Household/InvitationController.php:94
* @route '/household/invitations/{invitation}'
*/
destroy.delete = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Household\InvitationController::resend
* @see app/Http/Controllers/Household/InvitationController.php:106
* @route '/household/invitations/{invitation}/resend'
*/
export const resend = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

resend.definition = {
    methods: ["post"],
    url: '/household/invitations/{invitation}/resend',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\InvitationController::resend
* @see app/Http/Controllers/Household/InvitationController.php:106
* @route '/household/invitations/{invitation}/resend'
*/
resend.url = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { invitation: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { invitation: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            invitation: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        invitation: typeof args.invitation === 'object'
        ? args.invitation.id
        : args.invitation,
    }

    return resend.definition.url
            .replace('{invitation}', parsedArgs.invitation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\InvitationController::resend
* @see app/Http/Controllers/Household/InvitationController.php:106
* @route '/household/invitations/{invitation}/resend'
*/
resend.post = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

const invitations = {
    store: Object.assign(store, store),
    destroy: Object.assign(destroy, destroy),
    resend: Object.assign(resend, resend),
}

export default invitations