import { ProjectForm } from "@/components/project-form";
import { PageHeader } from "@/components/page-header";

export default function NewProjectPage() { return <><PageHeader eyebrow="Projects / New" title="Create a project" description="Give the work a clear owner, shape, and finish line." /><section className="form-panel"><ProjectForm /></section></>; }