import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ProposalController::index
* @see app/Http/Controllers/ProposalController.php:34
* @route '/proposals'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/proposals',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProposalController::index
* @see app/Http/Controllers/ProposalController.php:34
* @route '/proposals'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProposalController::index
* @see app/Http/Controllers/ProposalController.php:34
* @route '/proposals'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ProposalController::index
* @see app/Http/Controllers/ProposalController.php:34
* @route '/proposals'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProposalController::run
* @see app/Http/Controllers/ProposalController.php:64
* @route '/proposals/run'
*/
export const run = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: run.url(options),
    method: 'post',
})

run.definition = {
    methods: ["post"],
    url: '/proposals/run',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProposalController::run
* @see app/Http/Controllers/ProposalController.php:64
* @route '/proposals/run'
*/
run.url = (options?: RouteQueryOptions) => {
    return run.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProposalController::run
* @see app/Http/Controllers/ProposalController.php:64
* @route '/proposals/run'
*/
run.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: run.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProposalController::accept
* @see app/Http/Controllers/ProposalController.php:80
* @route '/proposals/{proposal}'
*/
export const accept = (args: { proposal: number | { id: number } } | [proposal: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: accept.url(args, options),
    method: 'patch',
})

accept.definition = {
    methods: ["patch"],
    url: '/proposals/{proposal}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ProposalController::accept
* @see app/Http/Controllers/ProposalController.php:80
* @route '/proposals/{proposal}'
*/
accept.url = (args: { proposal: number | { id: number } } | [proposal: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { proposal: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { proposal: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            proposal: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        proposal: typeof args.proposal === 'object'
        ? args.proposal.id
        : args.proposal,
    }

    return accept.definition.url
            .replace('{proposal}', parsedArgs.proposal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProposalController::accept
* @see app/Http/Controllers/ProposalController.php:80
* @route '/proposals/{proposal}'
*/
accept.patch = (args: { proposal: number | { id: number } } | [proposal: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: accept.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\ProposalController::reject
* @see app/Http/Controllers/ProposalController.php:99
* @route '/proposals/{proposal}'
*/
export const reject = (args: { proposal: number | { id: number } } | [proposal: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: reject.url(args, options),
    method: 'delete',
})

reject.definition = {
    methods: ["delete"],
    url: '/proposals/{proposal}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ProposalController::reject
* @see app/Http/Controllers/ProposalController.php:99
* @route '/proposals/{proposal}'
*/
reject.url = (args: { proposal: number | { id: number } } | [proposal: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { proposal: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { proposal: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            proposal: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        proposal: typeof args.proposal === 'object'
        ? args.proposal.id
        : args.proposal,
    }

    return reject.definition.url
            .replace('{proposal}', parsedArgs.proposal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProposalController::reject
* @see app/Http/Controllers/ProposalController.php:99
* @route '/proposals/{proposal}'
*/
reject.delete = (args: { proposal: number | { id: number } } | [proposal: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: reject.url(args, options),
    method: 'delete',
})

const ProposalController = { index, run, accept, reject }

export default ProposalController