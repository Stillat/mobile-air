import SwiftUI

/// Ordered, opt-in view decorators supplied by native plugins.
final class NativeNodeDecoratorRegistry {
    static let shared = NativeNodeDecoratorRegistry()
    typealias Decorator = (_ node: NativeUINode, _ content: AnyView) -> AnyView

    private let lock = NSLock()
    private var decorators: [String: Decorator] = [:]
    private var order: [String] = []
    private var snapshot: [Decorator] = []

    private init() {}

    func register(_ name: String, decorator: @escaping Decorator) {
        lock.lock(); defer { lock.unlock() }
        if decorators[name] == nil { order.append(name) }
        decorators[name] = decorator
        snapshot = order.compactMap { decorators[$0] }
    }

    func unregister(_ name: String) {
        lock.lock(); defer { lock.unlock() }
        decorators.removeValue(forKey: name)
        order.removeAll { $0 == name }
        snapshot = order.compactMap { decorators[$0] }
    }

    var hasDecorators: Bool {
        lock.lock(); defer { lock.unlock() }
        return !snapshot.isEmpty
    }

    func decorate(node: NativeUINode, content: AnyView) -> AnyView {
        lock.lock(); let current = snapshot; lock.unlock()
        return current.reduce(content) { view, decorator in decorator(node, view) }
    }
}

struct NativeNodeDecorationModifier: ViewModifier {
    let node: NativeUINode

    @ViewBuilder
    func body(content: Content) -> some View {
        if NativeNodeDecoratorRegistry.shared.hasDecorators {
            NativeNodeDecoratorRegistry.shared.decorate(node: node, content: AnyView(content))
        } else {
            content
        }
    }
}
