import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\AssistantController::messages
* @see app/Http/Controllers/AssistantController.php:41
* @route '/assistant/messages'
*/
export const messages = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: messages.url(options),
    method: 'post',
})

messages.definition = {
    methods: ["post"],
    url: '/assistant/messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AssistantController::messages
* @see app/Http/Controllers/AssistantController.php:41
* @route '/assistant/messages'
*/
messages.url = (options?: RouteQueryOptions) => {
    return messages.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AssistantController::messages
* @see app/Http/Controllers/AssistantController.php:41
* @route '/assistant/messages'
*/
messages.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: messages.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AssistantController::conversation
* @see app/Http/Controllers/AssistantController.php:200
* @route '/assistant/conversation'
*/
export const conversation = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: conversation.url(options),
    method: 'get',
})

conversation.definition = {
    methods: ["get","head"],
    url: '/assistant/conversation',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AssistantController::conversation
* @see app/Http/Controllers/AssistantController.php:200
* @route '/assistant/conversation'
*/
conversation.url = (options?: RouteQueryOptions) => {
    return conversation.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AssistantController::conversation
* @see app/Http/Controllers/AssistantController.php:200
* @route '/assistant/conversation'
*/
conversation.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: conversation.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AssistantController::conversation
* @see app/Http/Controllers/AssistantController.php:200
* @route '/assistant/conversation'
*/
conversation.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: conversation.url(options),
    method: 'head',
})

const assistant = {
    messages: Object.assign(messages, messages),
    conversation: Object.assign(conversation, conversation),
}

export default assistant