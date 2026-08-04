import Foundation

/// Opt-in observation seam for the decoded native tree.
final class NativeTreeObserverRegistry {
    static let shared = NativeTreeObserverRegistry()

    struct Subscription: Hashable { fileprivate let id: Int }

    private let lock = NSLock()
    private var sequence = 0
    private var observers: [Int: (NativeUITree) -> Void] = [:]
    private var latestTree: NativeUITree?

    private init() {}

    func observe(_ observer: @escaping (NativeUITree) -> Void) -> Subscription {
        lock.lock()
        sequence &+= 1
        let subscription = Subscription(id: sequence)
        observers[sequence] = observer
        let replay = latestTree
        lock.unlock()
        if let replay { observer(replay) }
        return subscription
    }

    func unsubscribe(_ subscription: Subscription) {
        lock.lock(); defer { lock.unlock() }
        observers.removeValue(forKey: subscription.id)
    }

    func publish(_ tree: NativeUITree) {
        lock.lock()
        latestTree = tree
        guard !observers.isEmpty else { lock.unlock(); return }
        let current = Array(observers.values)
        lock.unlock()
        for observer in current { observer(tree) }
    }
}
