package com.nativephp.mobile.ui.nativerender

import java.util.concurrent.atomic.AtomicInteger

/** Opt-in observation seam for the decoded native tree. */
object NativeTreeObserverRegistry {
    data class Subscription internal constructor(internal val id: Int)

    private val sequence = AtomicInteger(0)
    private val lock = Any()
    private val observers = linkedMapOf<Int, (NativeUITree) -> Unit>()
    @Volatile private var latestTree: NativeUITree? = null
    @Volatile private var hasObservers = false

    fun observe(observer: (NativeUITree) -> Unit): Subscription {
        val id = sequence.incrementAndGet()
        val replay = synchronized(lock) {
            observers[id] = observer
            hasObservers = true
            latestTree
        }
        replay?.let(observer)
        return Subscription(id)
    }

    fun unsubscribe(subscription: Subscription) {
        synchronized(lock) {
            observers.remove(subscription.id)
            hasObservers = observers.isNotEmpty()
        }
    }

    internal fun publish(tree: NativeUITree) {
        latestTree = tree
        if (!hasObservers) return
        val current = synchronized(lock) { observers.values.toList() }
        current.forEach { observer -> runCatching { observer(tree) } }
    }
}
