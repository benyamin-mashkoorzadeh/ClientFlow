import { TaskForm } from "@/components/task-form";
import { PageHeader } from "@/components/page-header";

export default function NewTaskPage() { return <><PageHeader eyebrow="Tasks / New" title="Create a task" description="Turn the next important step into something visible." /><section className="form-panel"><TaskForm /></section></>; }